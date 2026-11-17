<?php

namespace Tests\Feature\Matching;

use App\Enums\PodUser\State;
use App\Jobs\Matching\GenerateMatchesForEmployee;
use App\Jobs\Matching\GenerateMatchesForPod;
use App\Models\PodMatch;
use App\Models\PodUser;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/** UNA-016: after-commit dispatch from profile and POD writes, and isolation from onboarding data. */
class MatchGenerationTriggersTest extends WorkflowTestCase
{
    private function profiles(): ProfileService
    {
        return app(ProfileService::class);
    }

    /** Forget recorded jobs and release their unique locks between steps of one test. */
    private function resetQueue(): void
    {
        $lock = new \Illuminate\Bus\UniqueLock(app(\Illuminate\Contracts\Cache\Repository::class));
        foreach ([GenerateMatchesForEmployee::class, GenerateMatchesForPod::class, \App\Jobs\Matching\GenerateEmployeePodMatch::class] as $class) {
            Bus::dispatched($class)->each(fn ($job) => $lock->release($job));
        }
        Bus::fake();
    }

    private function employeeJobs(int $userId): int
    {
        return Bus::dispatched(GenerateMatchesForEmployee::class, fn ($job) => $job->userId === $userId)->count();
    }

    // ------------------------------------------------------------------ employee triggers

    public function test_reaching_onboarding_eligibility_dispatches_after_commit(): void
    {
        $user = $this->qualifiedEmployee();
        // 70%: no summary, no links, no education (the minimum is 80%).
        Profile::where('user_id', $user->id)->update(['about' => null, 'linkedin_url' => null]);
        $user->profile->educations()->delete();
        $this->assertFalse(app(\App\Services\Profile\ProfileCompletionService::class)->isEligible($user->fresh()));
        $this->resetQueue();

        $this->profiles()->saveProfile($user->fresh(), ['about' => 'Backend engineer']);
        $this->assertSame(1, $this->employeeJobs($user->id), 'first reaching eligibility');

        $this->resetQueue();
        $this->profiles()->saveProfile($user->fresh(), ['about' => 'Another summary']);
        $this->assertSame(0, $this->employeeJobs($user->id), 'about text alone does not affect matching');
    }

    public function test_matching_relevant_profile_changes_dispatch_and_irrelevant_ones_do_not(): void
    {
        $user = $this->qualifiedEmployee();
        $skills = $user->skills->map(fn ($s) => ['skill_id' => $s->id, 'proficiency' => $s->pivot->proficiency])->values()->all();

        $relevant = [
            'city' => ['city' => 'Kandy'],
            'timezone' => ['timezone' => 'Asia/Dubai'],
            'hours' => ['hours_per_week' => 25],
            'availability' => ['availability_status' => 'within_one_month'],
            'role' => ['job_title_id' => $this->designer->id],
            'work mode' => ['preferred_work_mode' => 'onsite'],
            'project types' => ['sync' => ['project_types'], 'project_types' => ['hourly']],
            'preferred roles' => ['sync' => ['preferred_roles'], 'preferred_role_ids' => [$this->role->id]],
            'proficiency' => ['sync' => ['skills'], 'skills' => array_map(fn ($s) => ['proficiency' => 'expert'] + $s, $skills)],
            'experience' => ['sync' => ['experiences'], 'experiences' => [['employer' => 'Acme', 'job_title' => 'Dev', 'job_title_id' => $this->role->id, 'start_date' => '2020-01-01', 'is_current' => true]]],
        ];
        foreach ($relevant as $what => $data) {
            $this->resetQueue();
            $this->profiles()->saveProfile($user->fresh(), $data);
            $this->assertSame(1, $this->employeeJobs($user->id), $what);
        }

        $irrelevant = [
            'phone' => ['phone_number' => '+94 77 000 0000'],
            'zip' => ['zip_code' => '00100'],
            'address' => ['address' => '1 Main Street'],
            'links' => ['linkedin_url' => 'https://www.linkedin.com/in/other', 'github_url' => 'https://github.com/other', 'website' => 'https://other.example.test'],
            'portfolio' => ['sync' => ['portfolios'], 'portfolios' => [['title' => 'Site', 'url' => 'https://example.test']]],
            'certifications' => ['sync' => ['certifications'], 'certifications' => [['name' => 'AWS']]],
            'unchanged skills' => ['sync' => ['skills'], 'skills' => array_map(fn ($s) => ['proficiency' => 'expert'] + $s, $skills)],
        ];
        foreach ($irrelevant as $what => $data) {
            $this->resetQueue();
            $this->profiles()->saveProfile($user->fresh(), $data);
            $this->assertSame(0, $this->employeeJobs($user->id), $what);
        }
    }

    public function test_a_rolled_back_profile_update_dispatches_nothing(): void
    {
        $user = $this->qualifiedEmployee();
        $this->resetQueue();

        try {
            DB::transaction(function () use ($user) {
                $this->profiles()->saveProfile($user->fresh(), ['city' => 'Galle']);
                Bus::assertNotDispatched(GenerateMatchesForEmployee::class);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        Bus::assertNotDispatched(GenerateMatchesForEmployee::class);
        $this->assertSame('Colombo', Profile::where('user_id', $user->id)->value('city'));
    }

    public function test_legacy_api_profile_writes_trigger_too(): void
    {
        $user = $this->qualifiedEmployee();
        $this->resetQueue();
        $this->profiles()->updateProfile($user->profile, ['city' => 'Kandy']);
        $this->assertSame(1, $this->employeeJobs($user->id));

        $this->resetQueue();
        $this->profiles()->updateProfile($user->fresh()->profile, ['phone_number' => '+94 11 111 1111']);
        $this->assertSame(0, $this->employeeJobs($user->id));

        $this->resetQueue();
        $this->profiles()->updateProfile($user->fresh()->profile, ['skill_ids' => [$this->php->id]]);
        $this->assertSame(1, $this->employeeJobs($user->id), 'legacy skill sync');
    }

    public function test_an_incomplete_profile_invalidates_recommendations_but_keeps_memberships(): void
    {
        $this->configurePod('automatic', 0);
        $requirement = $this->perfectRequirement();
        $user = $this->qualifiedEmployee();
        $this->apply($requirement, $user);
        $this->assertSame(State::OUTER_CIRCLE, PodUser::where('user_id', $user->id)->sole()->state);
        $memberships = $this->memberships();
        $this->resetQueue();

        $this->profiles()->saveProfile($user->fresh(), ['about' => '', 'linkedin_url' => '', 'sync' => ['educations'], 'educations' => []]);
        $this->assertSame(1, $this->employeeJobs($user->id), 'losing eligibility is a matching change');
        $this->assertSame(['invalidated_incomplete_profile'], array_column($this->drainMatchingJobs(), 1));

        $this->assertSame(0, PodMatch::where('user_id', $user->id)->count(), 'recommendations invalidated');
        $this->assertEquals($memberships, $this->memberships(), 'membership, access and history untouched');
    }

    // ------------------------------------------------------------------ POD triggers

    public function test_published_requirement_changes_dispatch_and_drafts_do_not(): void
    {
        $pod = $this->configurePod('recommend_only', 70);
        $requirement = $this->perfectRequirement();
        $other = $this->perfectRequirement();
        $row = fn ($r, array $o = []) => $this->requirementRow($r, $o);
        $save = function (array $rows) use ($pod) {
            $this->resetQueue();
            $this->pods()->updateDraft($pod, $this->company, ['requirements_present' => 1, 'requirements' => $rows]);

            return $this->podJobsFor($pod->id);
        };

        $this->assertSame(1, $save([$row($requirement, ['skill_ids' => [$this->php->id, $this->sql->id]]), $row($other)]), 'skills');
        $this->assertSame(1, $save([$row($requirement, ['minimum_skill_proficiency' => 'expert']), $row($other)]), 'proficiency');
        $this->assertSame(1, $save([$row($requirement, ['project_type' => 'hourly', 'mandatory_criteria' => ['project_type']]), $row($other)]), 'mandatory');
        $this->assertSame(1, $save([$row($requirement->fresh()), $row($other, ['is_active' => 0])]), 'inactivated');
        $this->assertSame(1, $save([$row($requirement->fresh()), $row($other->fresh(), ['is_active' => 1])]), 'activated');
        $this->assertSame(1, $save([$row($requirement->fresh()), $row($other->fresh()), ['job_title_id' => $this->engineer->id, 'headcount' => 1, 'skill_ids' => [$this->php->id], 'is_active' => 1]]), 'added');
        $this->assertSame(1, $save([$row($requirement->fresh())]), 'removed');
        $this->assertSame(0, $save([$row($requirement->fresh(), ['headcount' => 7, 'description' => 'Notes'])]), 'headcount/notes only');

        $this->resetQueue();
        $this->pods()->updateDraft($pod, $this->company, ['matching_mode' => 'automatic']);
        $this->assertSame(1, $this->podJobsFor($pod->id), 'mode');

        // A draft stores configuration but generates nothing.
        $draft = $this->pods()->createDraft($this->company, ['title' => 'Draft', 'matching_mode' => 'automatic']);
        $this->resetQueue();
        $this->pods()->updateDraft($draft, $this->company, ['minimum_match_threshold' => 10, 'requirements_present' => 1, 'requirements' => [
            ['job_title_id' => $this->engineer->id, 'headcount' => 1, 'skill_ids' => [$this->php->id], 'is_active' => 1],
        ]]);
        Bus::assertNotDispatched(GenerateMatchesForPod::class);
    }

    // ------------------------------------------------------------------ onboarding isolation

    public function test_an_enqueue_failure_after_commit_keeps_the_saved_profile(): void
    {
        $user = $this->qualifiedEmployee();
        $this->resetQueue();
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('queue down'));

        $saved = $this->profiles()->saveProfile($user->fresh(), ['city' => 'Jaffna', 'hours_per_week' => 30]);

        $this->assertSame(['Jaffna', 30], [$saved->city, $saved->hours_per_week]);
        $this->assertSame('Jaffna', Profile::where('user_id', $user->id)->value('city'), 'committed, not rolled back');
        $this->assertTrue(app(\App\Services\Profile\ProfileCompletionService::class)->isEligible($user->fresh()));
    }

    public function test_matching_jobs_never_touch_onboarding_state(): void
    {
        $workspace = $this->completeBusinessProfile($this->company);
        $workspace->forceFill(['business_onboarding_completed_at' => '2026-01-01 00:00:00'])->save();
        $pod = $this->configuredDraft();
        $this->pods()->publish($pod, $this->company);
        $user = $this->qualifiedEmployee();
        $user->profile->forceFill(['job_title_id' => $this->role->id])->save();
        $onboarding = fn () => [
            DB::table('workspaces')->get(['id', 'business_onboarding_completed_at', 'business_onboarding_step', 'business_onboarding_started_at'])->toArray(),
            DB::table('pods')->get(['id', 'status', 'published_at', 'configuration_completed_at', 'configuration_started_at'])->toArray(),
            DB::table('profiles')->orderBy('id')->get()->toArray(),
        ];
        $before = $onboarding();

        $this->drainMatchingJobs();  // publication
        GenerateMatchesForEmployee::dispatch($user->id, now()->toIso8601String());
        $this->drainMatchingJobs();  // employee
        DB::statement("CREATE TRIGGER fail_pair BEFORE INSERT ON pod_match_reasons BEGIN SELECT RAISE(ABORT, 'injected'); END");
        GenerateMatchesForPod::dispatch($pod->id, now()->addMinute()->toIso8601String());
        try {
            $this->drainMatchingJobs(); // a failing pair job
            $this->fail('the pair job failure must escape');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertEquals($before, $onboarding(), 'no matching path writes onboarding or profile state');
        $this->assertSame(State::PENDING_BUSINESS_APPROVAL, PodUser::where('user_id', $user->id)->sole()->state);
    }
}
