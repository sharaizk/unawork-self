<?php

namespace Tests\Feature\Authorization;

use App\Enums\User\UserRole;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Policies\UserWorkspacePolicy;
use App\Services\PodService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Company-workspace join requests (company.join.accept / company.join.decline) can only be
 * decided by the workspace owner or an active users_edit manager - never by the requester,
 * an unrelated user, a plain member, or an administrator without a role there. In-memory SQLite only.
 */
class WorkspaceJoinRequestAuthorizationTest extends TestCase
{
    use UsesInMemorySqlite;

    private User $owner;
    private Workspace $workspace;
    private User $requester;
    private UserWorkspace $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Mail::fake();
        Bus::fake();

        [$this->owner, $this->workspace] = $this->registered('owner', UserRole::COMPANY);
        [$this->requester] = $this->registered('requester', UserRole::EMPLOYEE);
        $this->actingAs($this->requester)->get(route('company.join', $this->workspace->id))->assertRedirect();
        $this->request = UserWorkspace::where('workspace_id', $this->workspace->id)->where('user_id', $this->requester->id)->sole();
    }

    public function test_policy_is_registered_for_user_workspaces(): void
    {
        $this->assertInstanceOf(UserWorkspacePolicy::class, Gate::getPolicyFor(UserWorkspace::class));
    }

    public function test_owner_can_accept(): void
    {
        $this->actingAs($this->owner)->get(route('company.join.accept', $this->request))
            ->assertRedirect(route('users.index', ['slug' => $this->workspace->slug]));

        $this->assertSame('active', $this->request->fresh()->status);
    }

    public function test_active_users_edit_manager_can_decline(): void
    {
        $manager = $this->member('manager', 'active', ['users_index', 'users_edit']);

        $this->actingAs($manager)->get(route('company.join.decline', $this->request))->assertRedirect();

        $this->assertSame('declined', $this->request->fresh()->status);
    }

    public function test_unauthorized_users_get_403_and_nothing_changes(): void
    {
        [$outsider] = $this->registered('outsider', UserRole::COMPANY);
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $denied = [
            'the requester' => $this->requester,
            'an unrelated user' => $outsider,
            'a plain member' => $this->member('member', 'active', ['users_index']),
            'a pending users_edit member' => $this->member('pending', 'employee_pending', ['users_edit']),
            'an administrator with no role here' => $admin,
        ];
        $before = $this->state();

        foreach ($denied as $who => $user) {
            foreach (['company.join.accept', 'company.join.decline'] as $route) {
                $this->actingAs($user)->get(route($route, $this->request))->assertForbidden();
            }
            $this->assertEquals($before, $this->state(), "{$who} changed the request");
        }
        $this->assertSame('workspace_pending', $this->request->fresh()->status);
    }

    public function test_pod_workspace_rows_cannot_be_moderated_through_these_routes(): void
    {
        $pod = app(PodService::class)->createPod(['title' => 'Pod', 'description' => 'd', 'created_by' => $this->owner->id]);
        $podMembership = UserWorkspace::create(['user_id' => $this->requester->id, 'workspace_id' => $pod->workspace->id, 'permission' => 'Member', 'status' => 'workspace_pending']);

        $this->actingAs($this->owner)->get(route('company.join.accept', $podMembership))->assertForbidden();

        $this->assertSame('workspace_pending', $podMembership->fresh()->status);
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{0: User, 1: Workspace} */
    private function registered(string $name, UserRole $role): array
    {
        $user = User::createWithAccountRole(['name' => $name, 'email' => "{$name}@example.test", 'plan' => 1, 'email_verified_at' => now()], $role);
        $workspace = Workspace::create(['name' => "{$name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner'])->assignAllPermissionsToCreator();
        $user->forceFill(['currant_workspace' => $workspace->id])->save();

        return [$user->fresh(), $workspace->fresh()];
    }

    private function member(string $name, string $status, array $permissions): User
    {
        [$user] = $this->registered($name, UserRole::EMPLOYEE);
        UserWorkspace::create(['user_id' => $user->id, 'workspace_id' => $this->workspace->id, 'permission' => 'Member', 'status' => $status])
            ->givePermissionTo($permissions);

        return $user;
    }

    private function state(): array
    {
        return [
            'request' => UserWorkspace::find($this->request->id)?->only(['status', 'is_active']),
            'permissions' => DB::table('model_has_permissions')->where('model_id', $this->request->id)->count(),
            'notifications' => Notification::orderBy('id')->get(['type', 'user_id', 'related_id', 'is_read'])->toArray(),
        ];
    }
}
