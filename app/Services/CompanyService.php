<?php

namespace App\Services;

use App\Models\User;
use App\Enums\User\UserRole;

class CompanyService
{
    public function getCompanies(?string $country = null, ?int $limit = null)
    {
        $query = User::role(UserRole::COMPANY->value);

        if ($country) {
            $query->whereHas('profile', function($query) use ($country) {
                $query->where('country', $country);
            });
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->with(['workspacesOwner' => function ($query) {
            $query->where('ownerable_type', '!=', 'App\Models\Pod');
        }])->get()->pluck('workspacesOwner')->flatten();
    }
}