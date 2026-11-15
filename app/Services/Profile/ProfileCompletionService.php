<?php

namespace App\Services\Profile;

use App\Contracts\HasAccountType;
use App\Enums\Profile\AvailabilityStatus;
use App\Models\Profile;
use App\Models\User;

/**
 * The single authoritative implementation of employee profile completion (UNA-007).
 *
 * Ten equally weighted sections, each worth 0 or 10 points. The score is always calculated from
 * the structured profile data (never stored), so it cannot go stale. Only employees are subject to
 * the minimum (config('profile.minimum_completion')); every other account type is eligible.
 *
 * Relations are eager loaded with loadMissing(): pass a User whose profile relations are already
 * loaded (e.g. a search result) to avoid extra queries, or a fresh User to read current data.
 */
class ProfileCompletionService
{
    public const SECTION_WEIGHT = 10;

    /** Relations (relative to User) the score needs. */
    public const RELATIONS = [
        'skills',
        'profile.experiences',
        'profile.educations',
        'profile.certifications',
        'profile.portfolios',
        'profile.preferredRoles',
        'profile.projectTypes',
    ];

    public function percentage(User $user): int
    {
        return $this->score($this->sections($user));
    }

    /**
     * @return array{percentage:int, required:int, eligible:bool, subject:bool,
     *     sections: array<string, array{label:string, weight:int, complete:bool, missing:string[]}>}
     */
    public function breakdown(User $user): array
    {
        $sections = $this->sections($user);
        $percentage = $this->score($sections);
        $subject = $this->isSubject($user);

        return [
            'percentage' => $percentage,
            'required' => $this->required(),
            'eligible' => !$subject || $percentage >= $this->required(),
            'subject' => $subject,
            'sections' => $sections,
        ];
    }

    /** Employees need the minimum; companies, administrators and clients are always eligible. */
    public function isEligible(HasAccountType $account): bool
    {
        if (!$account instanceof User || !$this->isSubject($account)) {
            return true;
        }

        return $this->percentage($account) >= $this->required();
    }

    /** An employee below the minimum, who should be sent to /my-profile. */
    public function requiresOnboarding(?HasAccountType $account): bool
    {
        return $account !== null && !$this->isEligible($account);
    }

    public function required(): int
    {
        return (int) config('profile.minimum_completion', 80);
    }

    public function isSubject(HasAccountType $account): bool
    {
        return $account->isEmployee();
    }

    // ------------------------------------------------------------------ sections

    /** @return array<string, array{label:string, weight:int, complete:bool, missing:string[]}> */
    private function sections(User $user): array
    {
        $user->loadMissing(self::RELATIONS);
        /** @var Profile $profile */
        $profile = $user->profile;
        $filled = fn ($value) => trim((string) $value) !== '';
        $minimumSkills = (int) config('profile.minimum_skills', 3);

        $checks = [
            'summary' => [__('Professional summary'), [
                __('Write a professional summary') => $filled($profile->about),
            ]],
            'headline' => [__('Current role and experience'), [
                __('Select your current job title') => $profile->job_title_id !== null,
                __('Enter your total years of experience') => $profile->years_experience !== null,
            ]],
            'location' => [__('Location and timezone'), [
                __('Enter your country') => $filled($profile->country),
                __('Enter your city') => $filled($profile->city),
                __('Select a valid timezone') => $this->validTimezone($profile->timezone),
            ]],
            'availability' => [__('Availability'), [
                __('Select your availability') => $profile->availability_status !== null,
                __('Enter the date you are available from') => !($profile->availability_status instanceof AvailabilityStatus && $profile->availability_status->requiresDate()) || $profile->available_from !== null,
                __('Enter your hours per week') => $profile->hours_per_week !== null,
            ]],
            'skills' => [__('Skills'), [
                __('Add at least :count skills with a proficiency level', ['count' => $minimumSkills]) => $user->skills->whereNotNull('pivot.proficiency')->count() >= $minimumSkills,
            ]],
            'experience' => [__('Employment experience'), [
                __('Add at least one employment experience') => $profile->experiences->isNotEmpty(),
            ]],
            'education' => [__('Education or certification'), [
                __('Add at least one education or certification record') => $profile->educations->isNotEmpty() || $profile->certifications->isNotEmpty(),
            ]],
            'preferred_roles' => [__('Preferred roles'), [
                __('Select at least one preferred role') => $profile->preferredRoles->isNotEmpty(),
            ]],
            'work_preferences' => [__('Work preferences'), [
                __('Select at least one preferred project type') => $profile->projectTypes->isNotEmpty(),
                __('Select your preferred work mode') => $profile->preferred_work_mode !== null,
                __('Select your preferred engagement type') => $profile->preferred_engagement_type !== null,
            ]],
            'links' => [__('Professional links'), [
                __('Add LinkedIn, GitHub, a website or a portfolio link') => $filled($profile->linkedin_url) || $filled($profile->github_url)
                    || $filled($profile->website) || $profile->portfolios->isNotEmpty(),
            ]],
        ];

        $sections = [];
        foreach ($checks as $key => [$label, $requirements]) {
            $missing = array_keys(array_filter($requirements, fn (bool $met) => !$met));
            $sections[$key] = [
                'label' => $label,
                'weight' => self::SECTION_WEIGHT,
                'complete' => $missing === [],
                'missing' => $missing,
            ];
        }

        return $sections;
    }

    private function score(array $sections): int
    {
        return min(100, count(array_filter($sections, fn (array $s) => $s['complete'])) * self::SECTION_WEIGHT);
    }

    private function validTimezone(?string $timezone): bool
    {
        return $timezone !== null && in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL), true);
    }
}
