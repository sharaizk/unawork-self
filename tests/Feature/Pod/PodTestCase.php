<?php

namespace Tests\Feature\Pod;

use App\Models\JobTitle;
use App\Models\Pod;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use App\Services\PodService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/** Shared setup for the UNA-011 POD lifecycle tests. In-memory SQLite only. */
abstract class PodTestCase extends TestCase
{
    use UsesInMemorySqlite;

    protected User $company;
    protected JobTitle $role;
    protected Skill $php;
    protected Skill $sql;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsingInMemorySqlite();
        $this->createIdentitySchema();
        $this->createAdminLayoutSchema();
        $this->runSeeders(\Database\Seeders\DatabaseSeeder::FOUNDATION_SEEDERS);
        DB::table('plans')->insert(['id' => 1, 'name' => 'Free Plan']);
        Storage::fake('public');
        Mail::fake();
        Bus::fake();

        $this->role = JobTitle::create(['title' => 'Backend Developer']);
        $this->php = Skill::create(['name' => 'PHP']);
        $this->sql = Skill::create(['name' => 'SQL']);
        $this->company = $this->account('company');
    }

    protected function account(string $type): User
    {
        $user = User::factory()->{$type}()->create(['plan' => 1, 'email_verified_at' => now()]);
        $workspace = Workspace::create(['name' => "{$user->name} WS", 'created_by' => $user->id, 'lang' => 'en']);
        $workspace->ownerable()->associate($user)->save();
        UserWorkspace::findOrCreateMembership($user->id, $workspace->id, ['permission' => 'Owner']);
        $user->forceFill(['currant_workspace' => $workspace->id])->save();

        return $user->fresh();
    }

    protected function pods(): PodService
    {
        return app(PodService::class);
    }

    /** Everything a fully configured (100%) POD needs, as setup-form input. */
    protected function completeConfiguration(): array
    {
        return [
            'title' => 'Platform Team',
            'description' => str_repeat('We build and run the platform for our clients. ', 2),
            'skills' => [$this->php->id],
            'matching_mode' => 'pending_approval',
            'requirements_present' => 1,
            'requirements' => [[
                'job_title_id' => $this->role->id, 'headcount' => 2, 'skill_ids' => [$this->php->id, $this->sql->id],
                'preferred_work_mode' => 'remote', 'preferred_engagement_type' => 'contract', 'is_active' => 1,
            ]],
        ];
    }

    protected function configuredDraft(?User $owner = null): Pod
    {
        $owner ??= $this->company;

        return $this->pods()->createDraft($owner, $this->completeConfiguration());
    }

    protected function snapshot(array $tables = ['pods', 'workspaces', 'pod_users', 'pod_user_state_histories', 'user_workspaces', 'model_has_permissions', 'notifications', 'pods_skills', 'pod_worker_requirements', 'pod_worker_requirement_skill', 'stages', 'bug_stages']): array
    {
        return collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy(DB::raw('1'))->get()->map(fn ($r) => (array) $r)->all()])->all();
    }
}
