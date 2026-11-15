<?php

/*
 * Business (company) onboarding (UNA-010). The completion percentage is always calculated by
 * App\Services\Company\BusinessOnboardingCompletionService from the company workspace; never stored.
 */

return [

    // A company may publish (create) a POD only at this completion.
    'required_completion' => 100,

    // Minimum length of the company description.
    'minimum_description_length' => 100,

    // Section weights (must add up to 100).
    'weights' => [
        'company' => 15,
        'industry' => 10,
        'contact' => 15,
        'online' => 10,
        'description' => 10,
        'requirements' => 20,
        'matching' => 10,
        'approvals' => 10,
    ],

    // Upper bounds for repeatable worker requirements.
    'max_worker_requirements' => 20,
    'max_headcount' => 500,

];
