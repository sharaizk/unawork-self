<?php

namespace App\Services;

use App\Models\User;
use App\Enums\User\UserRole;

class CompanyService
{
    public function getCompanies(?string $country = null)
    {
        $query = User::role(UserRole::COMPANY->value);

        if ($country) {
            $query->whereHas('profile', function($query) use ($country) {
                $query->where('country', $country);
            });
        }

        return $query->get();
    }
}