<?php

namespace App\Services;

use App\Enums\User\UserRole;
use App\Filters\Employee\EmployeeFilter;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EmployeeService
{
    public function getEmployees(array $data): Collection
    {
        $filter = new EmployeeFilter($data);
        $employees = User::employees();

        $employees = $filter->apply($employees->with(['profile', 'skills']));
        return $employees->get();
    }

    public function show(int $id)
    {
        try {
            return User::find($id);
        } catch (\Exception $e) {
            throw new \Exception(__($e->getMessage()));
        }
    }

    public function makeEmployee(User $user)
    {
        try {
            $user->assignAccountRole(UserRole::EMPLOYEE);
        } catch (\Exception $e) {
            throw new \Exception(__($e->getMessage()));
        }
    }
}