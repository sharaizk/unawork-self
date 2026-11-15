<?php

/*
 * Guided POD creation / publication (UNA-011). The configuration percentage is always calculated by
 * App\Services\Pod\PodConfigurationService from the POD; never stored.
 */

return [

    // A draft POD can be published only at this configuration percentage.
    'required_completion' => 100,

    'minimum_description_length' => 50,

    // Section weights (must add up to 100).
    'weights' => [
        'title' => 15,
        'description' => 15,
        'skills' => 20,
        'requirements' => 30,
        'matching' => 20,
    ],

    'max_title_length' => 255,
    'max_description_length' => 5000,
    'max_skills' => 30,
    'max_requirements' => 20,
    'max_headcount' => 500,

];
