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

        foreach ($this->filters() as $filter => $value) {
            if (method_exists($this, $filter)) {
                $this->$filter($value);
            }
        }

        return $this->builder;
    }

    public function filters(): array
    {
        return $this->request;
    }

    protected function name($value)
    {
        $this->builder->where('name', 'like', "%$value%");
    }

    protected function email($value)
    {
        $this->builder->where('email', 'like', "%$value%");
    }

    protected function country($value)
    {
        $this->builder->whereHas('profile', function ($query) use ($value) {
            $query->where('country', 'like', "%$value%");
        });
    }

    protected function state($value)
    {
        $this->builder->whereHas('profile', function ($query) use ($value) {
            $query->where('state', 'like', "%$value%");
        });
    }

    protected function city($value)
    {
        $this->builder->whereHas('profile', function ($query) use ($value) {
            $query->where('city', 'like', "%$value%");
        });
    }

    protected function zip_code($value)
    {
        $this->builder->whereHas('profile', function ($query) use ($value) {
            $query->where('zip_code', 'like', "%$value%");
        });
    }

    protected function address($value)
    {
        $this->builder->whereHas('profile', function ($query) use ($value) {
            $query->where('address', 'like', "%$value%");
        });
    }

    protected function phone_number($value)
    {
        $this->builder->whereHas('profile', function ($query) use ($value) {
            $query->where('phone_number', 'like', "%$value%");
        });
    }

    protected function skill_id($value)
    {
        $this->builder->whereHas('skills', function ($query) use ($value) {
            $query->where('skills.id', $value);
        });
    }
}
