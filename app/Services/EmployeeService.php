<?php

namespace App\Services;

use App\Enums\User\UserRole;
use App\Filters\Employee\EmployeeFilter;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EmployeeService
{
    /** Eager loads for employee listings and EmployeeResource (no N+1, UNA-007). */
    public const LISTING_RELATIONS = ['profile.industry', 'profile.jobTitle', 'profile.preferredRoles.jobTitle', 'profile.projectTypes', 'skills'];

    public function getEmployees(array $data): Collection
    {
        $filter = new EmployeeFilter($data);
        $employees = User::employees();

        $employees = $filter->apply($employees->with(self::LISTING_RELATIONS));
        return $employees->get();
    }

    public function show(int $id)
    {
        try {
            return User::with(self::LISTING_RELATIONS)->find($id);
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