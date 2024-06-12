<?php

namespace App\Services;

use App\Filters\Employee\EmployeeFilter;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EmployeeService
{
    public function getEmployees(array $data): Collection
    {
        $filter = new EmployeeFilter($data);
        $employees = User::where('plan', NULL)
            ->where('type', 'user');

        $employees = $filter->apply($employees->with(['profile', 'skills']));
        return $employees->get();
    }


}