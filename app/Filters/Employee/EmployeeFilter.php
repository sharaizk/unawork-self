<?php

namespace App\Filters\Employee;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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

        foreach ($this->filters() as $filter => $value) {
            if (method_exists($this, $filter)) {
                $this->$filter($value);
            }
        }

        // Select specific columns to avoid column name conflicts
        $this->builder->select('users.*', 'profiles.country', 'profiles.state', 'profiles.city', 'profiles.zip_code', 'profiles.address', 'profiles.phone_number', 'profiles.cv_file')
            ->distinct();
        
        return $this->builder;
    }

    public function filters(): array
    {
        return $this->request;
    }

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
        $this->builder->where('profiles.country', 'like', "%$value%");
    }

    protected function state(string $value): void
    {
        $this->builder->where('profiles.state', 'like', "%$value%");
    }

    protected function city(string $value): void
    {
        $this->builder->where('profiles.city', 'like', "%$value%");
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

    protected function job_title_id(string $value): void
    {
        $this->builder->where('profiles.job_title_id', $value);
    }

    protected function skill_ids(array $value): void
    {
        $this->builder->whereHas('skills', function ($query) use ($value) {
            $query->whereIn('skills.id', $value);
        });
    }
}
