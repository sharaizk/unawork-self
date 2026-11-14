<?php

namespace Database\Seeders;

use App\Enums\PodUser\Permission as PodUserPermission;
use App\Enums\PodUser\State;
use App\Enums\User\UserRole;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Enums\UserWorkspace\Status as WorkspaceStatus;
use App\Models\Notification;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use App\Services\PodService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL-ONLY demo data for manually testing UNA-001 (POD membership and project
 * claim authorization). Not called from DatabaseSeeder; deploys never run it.
 *
 *   php artisan db:seed --class=Database\\Seeders\\Una001AuthorizationDemoSeeder
 *
 * All demo users share the password in self::PASSWORD and use @example.test emails.
 * Re-running is refused while the demo users exist.
 */
class Una001AuthorizationDemoSeeder extends Seeder
{
    public const PASSWORD = 'Una001-Demo-Pass!';
    public const EMAIL_DOMAIN = 'example.test';

    public function run(): void
    {
        if (!app()->environment('local')) {
            $this->command?->error('Refusing to seed UNA-001 demo data outside APP_ENV=local.');
            return;
        }

        if (User::where('email', 'like', 'una001.%@' . self::EMAIL_DOMAIN)->exists()) {
            $this->command?->warn('UNA-001 demo users already exist; nothing seeded.');
            return;
        }

        DB::transaction(function () {
            $owner = $this->registerUser('owner', 'UNA001 Owner (company)', UserRole::COMPANY);
            $manager = $this->registerUser('manager', 'UNA001 Manager (users_edit)', UserRole::EMPLOYEE);
            $projectManager = $this->registerUser('projectmanager', 'UNA001 Project Manager (projects_edit)', UserRole::EMPLOYEE);
            $member = $this->registerUser('member', 'UNA001 Member (view only)', UserRole::EMPLOYEE);
            $claimant = $this->registerUser('claimant', 'UNA001 Claimant (pending project request)', UserRole::EMPLOYEE);
            $applicant = $this->registerUser('applicant', 'UNA001 Applicant (asked to join)', UserRole::EMPLOYEE);
            $invitee = $this->registerUser('invitee', 'UNA001 Invitee (invited)', UserRole::EMPLOYEE);
            $outsider = $this->registerUser('outsider', 'UNA001 Outsider (other POD owner)', UserRole::COMPANY);

            $podService = app(PodService::class);

            // Demo POD. Inner Circle is pre-enabled because the Free Plan allows 0 inner circles.
            $pod = $podService->createPod([
                'title' => 'UNA001 Demo POD',
                'description' => 'Local demo POD for UNA-001 authorization testing.',
                'created_by' => $owner->id,
            ]);
            $pod->forceFill(['inner_enabled' => true])->save();

            $outerCircle = [WorkspacePermission::USERS_INDEX, WorkspacePermission::NEWS_INDEX];
            $innerCircle = [...$outerCircle, WorkspacePermission::PROJECTS_INDEX, WorkspacePermission::ZOOM_MEETING_INDEX];

            $this->addMember($pod, $manager, State::OUTER_CIRCLE, WorkspaceStatus::ACTIVE, [...$outerCircle, WorkspacePermission::USERS_EDIT]);
            $this->addMember($pod, $projectManager, State::INNER_CIRCLE, WorkspaceStatus::ACTIVE, [...$innerCircle, WorkspacePermission::PROJECTS_EDIT]);
            $this->addMember($pod, $member, State::OUTER_CIRCLE, WorkspaceStatus::ACTIVE, $outerCircle);
            $this->addMember($pod, $claimant, State::INNER_CIRCLE, WorkspaceStatus::ACTIVE, $innerCircle);

            // Join request awaiting the POD (same shape as PodUserService::requestJoinPod).
            $request = $this->addMember($pod, $applicant, State::PENDING_BUSINESS_APPROVAL, WorkspaceStatus::EMPLOYEE_PENDING, $outerCircle);
            $this->notify('pod_request', $owner->id, $request);

            // Invitation awaiting the employee (same shape as PodUserService::inviteExistedUser).
            $this->addMember($pod, $invitee, State::MATCHED, WorkspaceStatus::EMPLOYEE_PENDING, $outerCircle);
            $this->notify('pod_invite', $invitee->id, $pod);

            // Projects in the POD workspace.
            $claimedProject = $this->createProject($pod, $owner, 'UNA001 Project with pending request');
            $this->createProject($pod, $owner, 'UNA001 Open Project');

            $claim = UserProject::create([
                'project_id' => $claimedProject->id,
                'user_id' => $claimant->id,
                'is_active' => 0,
                'status' => 'pending',
            ]);
            $this->notify('project_request', $owner->id, $claim);

            // A second POD for cross-POD ID substitution checks: the member has a pending
            // request there that the Demo POD's owner/manager must not be able to moderate.
            $otherPod = $podService->createPod([
                'title' => 'UNA001 Other POD',
                'description' => 'Second POD for cross-POD ID substitution checks.',
                'created_by' => $outsider->id,
            ]);
            $otherRequest = $this->addMember($otherPod, $member, State::PENDING_BUSINESS_APPROVAL, WorkspaceStatus::EMPLOYEE_PENDING, $outerCircle);
            $this->notify('pod_request', $outsider->id, $otherRequest);

            $this->command?->info(sprintf(
                'Seeded UNA-001 demo: Demo POD #%d, Other POD #%d, claim #%d, other-POD request #%d. Password: see %s::PASSWORD.',
                $pod->id, $otherPod->id, $claim->id, $otherRequest->id, self::class
            ));
        });
    }

    /** Mirrors RegisteredUserController::store (own workspace, Owner membership, role, Free Plan). */
    private function registerUser(string $key, string $name, UserRole $role): User
    {
        do {
            $code = rand(100000, 999999);
        } while (DB::table('users')->where('referral_code', $code)->exists());

        $user = User::createWithAccountRole([
            'name' => $name,
            'email' => "una001.{$key}@" . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 1,
            'lang' => 'en',
            'referral_code' => $code,
            'referral_used' => '0',
        ], $role);
        $user->forceFill(['email_verified_at' => now(), 'is_enable_login' => 1])->save();

        $workspace = Workspace::create([
            'lang' => 'en',
            'created_by' => $user->id,
            'name' => $name,
            'currency_code' => 'USD',
            'paypal_mode' => 'sandbox',
        ]);
        $workspace->ownerable()->associate($user);
        $workspace->save();

        $userWorkspace = new UserWorkspace();
        $userWorkspace->user_id = $user->id;
        $userWorkspace->workspace_id = $workspace->id;
        $userWorkspace->permission = 'Owner';
        $userWorkspace->save();
        $userWorkspace->assignAllPermissionsToCreator();

        $user->currant_workspace = $workspace->id;
        $user->save();

        User::userDefaultDataRegister($user);

        return $user;
    }

    /** Demo membership in an explicit state (UNA-006), with its initial history row. */
    private function addMember(Pod $pod, User $user, State $state, WorkspaceStatus $workspaceStatus, array $permissions): PodUser
    {
        $podUser = PodUser::forceCreate([
            'user_id' => $user->id,
            'pod_id' => $pod->id,
            'permission' => PodUserPermission::MEMBER->value,
            'state' => $state->value,
        ]);
        \App\Models\PodUserStateHistory::query()->forceCreate([
            'pod_user_id' => $podUser->id, 'from_state' => null, 'to_state' => $state->value, 'actor_id' => null, 'reason' => 'UNA-001 demo seed',
        ]);

        $workspace = $pod->workspace;
        $workspace->users()->attach($user->id, ['permission' => 'Member', 'is_active' => 1, 'status' => $workspaceStatus->value]);

        UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $user->id)->first()
            ->givePermissionTo(array_map(fn (WorkspacePermission $p) => $p->value, $permissions));

        return $podUser;
    }

    /** Mirrors ProjectController::store for the owner (without notifications or mail). */
    private function createProject(Pod $pod, User $owner, string $name): Project
    {
        $project = Project::create([
            'name' => $name,
            'status' => 'Ongoing',
            'description' => 'UNA-001 demo project.',
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'),
            'workspace' => $pod->workspace->id,
            'created_by' => $owner->id,
            'is_active' => 1,
        ]);
        $project->forceFill([
            'password' => '',
            'copylinksetting' => json_encode([
                'basic_details' => 'on', 'member' => 'on', 'client' => 'on', 'attachment' => 'on',
                'bug_report' => 'on', 'task' => 'on', 'progress' => 'on', 'activity' => 'on',
                'milestone' => 'on', 'password_protected' => 'off',
            ]),
        ])->save();

        UserProject::create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'permission' => json_encode(Utility::getAllPermission()),
        ]);

        return $project;
    }

    /** Same row Utility::sendNotification() writes, without the Pusher broadcast. */
    private function notify(string $type, int $userId, $obj): void
    {
        Notification::create([
            'workspace_id' => null,
            'user_id' => $userId,
            'project_id' => $obj->project_id ?? null,
            'related_id' => $obj->id ?? null,
            'type' => $type,
            'data' => json_encode($obj),
            'is_read' => 0,
        ]);
    }
}
