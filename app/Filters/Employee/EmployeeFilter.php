<?php

namespace App\Filters\Employee;

use Illuminate\Database\Eloquent\Builder;

class EmployeeFilter
{
    protected $request;
    protected $builder;

    public function __construct(array $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        // Left join with profiles table
        $this->builder->leftJoin('profiles', 'users.id', '=', 'profiles.user_id');

        $this->applyFilters($this->builder);

        // Select specific columns to avoid column name conflicts
        $this->builder->select('users.*', 'profiles.country', 'profiles.state', 'profiles.city', 'profiles.zip_code', 'profiles.address', 'profiles.phone_number', 'profiles.cv_file')
            ->distinct();
        
        return $this->builder;
    }

    public function filters(): array
    {
        return $this->request;
    }

    /**
     * Run only the filter methods on $builder (no profiles join / select). The structured UNA-007
     * filters use whereHas, so they also work on a plain User::employees() query.
     */
    public function applyFilters(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->filters() as $filter => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (in_array($filter, self::FILTERS, true)) {
                $this->$filter($value);
            }
        }

        return $this->builder;
    }

    /** Filter keys this class accepts (also guards against calling non-filter methods). */
    public const FILTERS = [
        'name', 'email', 'country', 'state', 'city', 'zip_code', 'address', 'phone_number', 'job_title_id', 'skill_ids',
        // UNA-007 structured matching
        'industry_id', 'skill_proficiency', 'min_years_experience', 'max_years_experience', 'preferred_role_ids', 'timezone',
        'availability_status', 'project_types', 'preferred_work_mode', 'preferred_engagement_type',
    ];

    protected function name(string $value): void
    {
        $this->builder->where('users.name', 'like', "%$value%");
    }

    protected function email(string $value): void
    {
        $this->builder->where('users.email', 'like', "%$value%");
    }

    protected function country(string $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->where('profiles.country', 'like', "%$value%"));
    }

    protected function state(string $value): void
    {
        $this->builder->where('profiles.state', 'like', "%$value%");
    }

    protected function city(string $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->where('profiles.city', 'like', "%$value%"));
    }

    protected function zip_code(string $value): void
    {
        $this->builder->where('profiles.zip_code', 'like', "%$value%");
    }

    protected function address(string $value): void
    {
        $this->builder->where('profiles.address', 'like', "%$value%");
    }

    protected function phone_number(string $value): void
    {
        $this->builder->where('profiles.phone_number', 'like', "%$value%");
    }

    protected function job_title_id(string|int $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->where('profiles.job_title_id', $value));
    }

    /** Any of the skills; combined with skill_proficiency when both are given. */
    protected function skill_ids(array $value): void
    {
        $proficiency = $this->proficiencies();
        $this->builder->whereHas('skills', function ($query) use ($value, $proficiency) {
            $query->whereIn('skills.id', $value);
            if ($proficiency) {
                $query->whereIn('user_skills.proficiency', $proficiency);
            }
        });
    }

    // ------------------------------------------------------------------ UNA-007 structured filters

    /** Any skill at one of these proficiencies (applied inside skill_ids when skill ids are given). */
    protected function skill_proficiency(string|array $value): void
    {
        if (!empty($this->request['skill_ids'])) {
            return;
        }
        $this->builder->whereHas('skills', fn ($q) => $q->whereIn('user_skills.proficiency', $this->proficiencies()));
    }

    /** The employee's primary industry. */
    protected function industry_id(string|int $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->where('profiles.industry_id', (int) $value));
    }

    protected function min_years_experience(string|int|float $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->where('profiles.years_experience', '>=', (float) $value));
    }

    protected function max_years_experience(string|int|float $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->where('profiles.years_experience', '<=', (float) $value));
    }

    protected function preferred_role_ids(array $value): void
    {
        $this->builder->whereHas('profile.preferredRoles', fn (Builder $q) => $q->whereIn('profile_preferred_roles.job_title_id', $value));
    }

    protected function timezone(string $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->where('profiles.timezone', $value));
    }

    protected function availability_status(string|array $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->whereIn('profiles.availability_status', (array) $value));
    }

    protected function project_types(string|array $value): void
    {
        $this->builder->whereHas('profile.projectTypes', fn (Builder $q) => $q->whereIn('profile_project_types.project_type', (array) $value));
    }

    protected function preferred_work_mode(string|array $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->whereIn('profiles.preferred_work_mode', (array) $value));
    }

    protected function preferred_engagement_type(string|array $value): void
    {
        $this->builder->whereHas('profile', fn (Builder $q) => $q->whereIn('profiles.preferred_engagement_type', (array) $value));
    }

    /** @return string[] */
    private function proficiencies(): array
    {
        return array_values(array_filter((array) ($this->request['skill_proficiency'] ?? [])));
    }
}
