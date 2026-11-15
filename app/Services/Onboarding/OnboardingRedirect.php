<?php

namespace App\Services\Onboarding;

use App\Contracts\HasAccountType;
use App\Services\Company\BusinessOnboardingCompletionService;
use App\Services\Profile\ProfileCompletionService;

/**
 * Where an account must go to finish onboarding, used after registration, email verification and
 * login (/check). Precedence: incomplete employee → /my-profile (UNA-007); incomplete company →
 * /company/onboarding (UNA-010); everyone else → null (keep the normal destination). Only those
 * entry points redirect, so the rest of the application stays usable.
 */
class OnboardingRedirect
{
    public function __construct(
        private ProfileCompletionService $profiles,
        private BusinessOnboardingCompletionService $companies,
    ) {
    }

    /** @return array{route:string, message:string}|null */
    public function for(?HasAccountType $account): ?array
    {
        if ($this->profiles->requiresOnboarding($account)) {
            return [
                'route' => 'my-profile.edit',
                'message' => __('Please complete your profile. :required% is required to access jobs and active POD circles.', ['required' => $this->profiles->required()]),
            ];
        }
        if ($this->companies->requiresOnboarding($account)) {
            return [
                'route' => 'company.onboarding.edit',
                'message' => __('Please complete your company profile. :required% is required to publish a POD.', ['required' => $this->companies->required()]),
            ];
        }

        return null;
    }
}
