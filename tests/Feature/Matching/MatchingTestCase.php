<?php

namespace Tests\Feature\Matching;

use App\Models\JobTitle;
use App\Models\Pod;
use App\Models\PodWorkerRequirement;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\Matching\PodMatchScorer;
use App\Services\Matching\PodMatchService;
use Carbon\CarbonImmutable;
use Tests\Feature\Pod\PodTestCase;

/**
 * Shared fixtures for the UNA-013 matching tests. In-memory SQLite only (PodTestCase refuses
 * anything else). Every evaluation uses the fixed time AT, so no test depends on the clock.
 */
abstract class MatchingTestCase extends PodTestCase
{
    protected const AT = '2026-06-15 12:00:00';

    protected JobTitle $designer;
    protected Skill $docker;
    protected Skill $laravel;
    protected ?Pod $pod = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->designer = JobTitle::create(['title' => 'Product Designer']);
        $this->docker = Skill::create(['name' => 'Docker']);
        $this->laravel = Skill::create(['name' => 'Laravel']);
    }

    protected function at(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::AT, 'UTC');
    }

    /**
     * A POD worker requirement with exactly the given attributes (everything else null).
     *
     * @param int[] $skillIds
     */
    protected function requirement(array $attributes = [], array $skillIds = []): PodWorkerRequirement
    {
        $this->pod ??= $this->pods()->createDraft($this->company, ['title' => 'Platform Team']);
        $requirement = new PodWorkerRequirement($attributes + ['is_active' => true, 'headcount' => 1]); // explicit attributes win
        $requirement->forceFill(['pod_id' => $this->pod->id])->save();
        $requirement->skills()->sync($skillIds);

        return $requirement->fresh();
    }

    /** The requirement every perfect-match test starts from (all nine criteria configured). */
    protected function fullRequirement(array $overrides = []): PodWorkerRequirement
    {
        return $this->requirement($overrides + [
            'job_title_id' => $this->role->id,
            'minimum_skill_proficiency' => 'advanced',
            'minimum_years_experience' => 2,
            'required_start_date' => '2026-07-01',
            'minimum_hours_per_week' => 30,
            'location' => 'Colombo, Sri Lanka',
            'timezone' => 'Asia/Colombo',
            'timezone_tolerance_hours' => 1,
            'project_type' => 'long_term',
            'preferred_work_mode' => 'remote',
            'preferred_engagement_type' => 'contract',
        ], [$this->php->id, $this->sql->id]);
    }

    /**
     * An employee with a profile.
     *
     * @param array<int, ?string> $skills skill id => proficiency (null = legacy row)
     * @param array<int, array> $experiences profile_experiences attributes
     * @param int[] $preferredRoles job title ids
     * @param string[] $projectTypes
     */
    protected function candidate(array $profile = [], array $skills = [], array $experiences = [], array $preferredRoles = [], array $projectTypes = []): User
    {
        $user = $this->account('employee');
        $model = Profile::where('user_id', $user->id)->first() ?? new Profile();
        $model->forceFill(['user_id' => $user->id] + $profile)->save();

        foreach ($skills as $skillId => $proficiency) {
            $user->skills()->attach($skillId, ['proficiency' => $proficiency]);
        }
        foreach ($experiences as $experience) {
            $model->experiences()->forceCreate(['profile_id' => $model->id, 'employer' => 'Acme', 'job_title' => 'Role'] + $experience);
        }
        foreach ($preferredRoles as $jobTitleId) {
            $model->preferredRoles()->forceCreate(['profile_id' => $model->id, 'job_title_id' => $jobTitleId]);
        }
        foreach ($projectTypes as $type) {
            $model->projectTypes()->forceCreate(['profile_id' => $model->id, 'project_type' => $type]);
        }

        return $user->fresh();
    }

    /** A candidate who satisfies fullRequirement() completely. */
    protected function perfectCandidate(array $profileOverrides = []): User
    {
        return $this->candidate($profileOverrides + [
            'job_title_id' => $this->role->id,
            'availability_status' => 'immediately',
            'hours_per_week' => 40,
            'city' => 'Colombo',
            'country' => 'Sri Lanka',
            'timezone' => 'Asia/Colombo',
            'preferred_work_mode' => 'remote',
            'preferred_engagement_type' => 'contract',
        ], [$this->php->id => 'expert', $this->sql->id => 'advanced'], [
            ['job_title_id' => $this->role->id, 'start_date' => '2023-01-01', 'is_current' => true],
        ], [], ['long_term']);
    }

    protected function score(PodWorkerRequirement $requirement, User $candidate): \App\Services\Matching\MatchResult
    {
        return app(PodMatchService::class)->score($requirement, $candidate, $this->at());
    }

    protected function scorer(): PodMatchScorer
    {
        return app(PodMatchScorer::class);
    }
}
