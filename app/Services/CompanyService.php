<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Enums\User\UserRole;

class CompanyService
{
    public function getCompanies(?string $country = null, ?int $limit = null)
    {
        $query = Workspace::where('ownerable_type', '!=', 'App\Models\Pod')
        ->whereHas('creater', function ($query) use ($country) {
            $query->role(UserRole::COMPANY->value);

            if ($country) {
                $query->where('country', $country);
            }
        });

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }
}