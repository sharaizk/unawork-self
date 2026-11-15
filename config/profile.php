<?php

/*
 * Employee profile onboarding (UNA-007).
 *
 * The completion percentage is always calculated by App\Services\Profile\ProfileCompletionService
 * from the structured profile data; it is never stored.
 */

return [

    // Minimum completion an employee needs to access jobs and enter an active POD circle.
    'minimum_completion' => (int) env('PROFILE_MINIMUM_COMPLETION', 80),

    // Number of proficiency-rated skills required by the skills section.
    'minimum_skills' => 3,

];
