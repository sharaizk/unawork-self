<?php

namespace Tests\Feature\Provisioning;

use App\Enums\User\UserRole;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Models\EmailTemplate;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserEmailTemplate;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\Provisioning\UserProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/** Shared setup and the account-structure contract for UNA-012 provisioning tests. In-memory SQLite only. */
abstract class ProvisioningTestCase extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        Schema::table('projects', function (Blueprint $t) {
            $t->string('status')->nullable();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('user_type')->nullable();
            $t->unsignedBigInteger('project_id')->nullable();
            $t->string('log_type')->nullable();
            $t->text('remark')->nullable();
            $t->timestamps();
        });
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        EmailTemplate::forceCreate(['name' => 'User Invited']);
        EmailTemplate::forceCreate(['name' => 'Project Assigned']);
        Mail::fake();
        Bus::fake();
    }

    protected function provisioning(): UserProvisioningService
    {
        return app(UserProvisioningService::class);
    }

    /** A pre-UNA-012 style account with its own workspace (fixture). */
    protected function account(string $type, string $email): User
    {
        $user = User::factory()->{$type}()->create(['email' => $email, 'plan' => 1, 'email_verified_at' => now()]);
        if ($type === 'administrator') {
            return $user;
        }
        $workspace = Workspace::create(['name' => "{$user->name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner'])->assignAllPermissionsToCreator();
        $user->forceFill(['currant_workspace' => $workspace->id])->save();

        return $user->fresh();
    }

    /** The provisioning contract every ordinary account must satisfy. */
    protected function assertAccountStructure(User $user, UserRole $role, string $onboardingRoute): Workspace
    {
        $user = $user->fresh();
        $this->assertSame([$role->value], $user->getRoleNames()->all(), 'exactly one persona role');
        $this->assertSame('user', $user->type);
        $this->assertSame(1, (int) $user->plan, 'default plan');
        $this->assertNull($user->plan_expire_date);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $user->referral_code);

        $owned = Workspace::where('created_by', $user->id)->where('ownerable_type', User::class)->where('ownerable_id', $user->id)->get();
        $this->assertCount(1, $owned, 'exactly one owned non-POD workspace');
        $workspace = $owned->first();

        $owner = UserWorkspace::where('user_id', $user->id)->where('workspace_id', $workspace->id)->sole();
        $this->assertSame('Owner', $owner->permission);
        $this->assertEqualsCanonicalizing(WorkspacePermission::values(), $owner->getPermissionNames()->all(), 'owner permissions');
        $this->assertSame($workspace->id, (int) $user->currant_workspace, 'current workspace is the owned one');
        $this->assertSame(EmailTemplate::count(), UserEmailTemplate::where('user_id', $user->id)->count(), 'default email templates');
        $this->assertSame($role === UserRole::EMPLOYEE ? 1 : 0, Profile::where('user_id', $user->id)->count(), 'employee profile');

        $onboarding = $this->provisioning()->onboarding($user);
        $this->assertSame([true, $onboardingRoute, $role->value], [$onboarding['required'], $onboarding['route'], $onboarding['type']]);
        $this->assertSame(0, $onboarding['percentage']);

        return $workspace;
    }
}
