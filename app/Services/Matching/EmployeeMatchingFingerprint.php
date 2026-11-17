<?php

namespace App\Services\Matching;

use App\Models\Profile;
use App\Models\User;
use App\Services\Profile\ProfileCompletionService;

/**
 * The single definition of which employee data affects matching (UNA-016). Profile writers take
 * a fingerprint before and after their change (inside their transaction) and queue
 * GenerateMatchesForEmployee only when it differs.
 *
 * Included: exactly what PodMatchScorer reads (skills + proficiency, primary role, preferred roles,
 * experiences' role and dates, years of experience, availability status/date, weekly hours, city,
 * state, country, timezone, project types, preferred work mode and engagement type) plus whether
 * the profile is eligible for matching (ProfileCompletionService), so reaching or losing the
 * profile minimum is a change too.
 *
 * Not included (no dispatch on their own): phone, ZIP/postal code, street address, CV, portfolio,
 * LinkedIn/GitHub/website, about text, industry, education, certifications, relocation/travel -
 * unless editing them changes eligibility.
 */
class EmployeeMatchingFingerprint
{
    public const PROFILE_FIELDS = [
        'job_title_id', 'years_experience', 'country', 'state', 'city', 'timezone',
        'availability_status', 'available_from', 'hours_per_week', 'preferred_work_mode', 'preferred_engagement_type',
    ];

    public function __construct(private ProfileCompletionService $completion)
    {
    }

    /** Fingerprint of the user's current (database) state; reads fresh data, ignoring loaded relations. */
    public function of(int $userId): string
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return 'missing';
        }
        $profile = Profile::query()->where('user_id', $userId)->with(['experiences', 'preferredRoles', 'projectTypes'])->first();

        $data = [
            'eligible' => $profile !== null && $this->completion->isEligible($user),
            'profile' => null,
            'skills' => $user->skills()->get()->mapWithKeys(fn ($s) => [(int) $s->id => $s->pivot->proficiency])->sortKeys()->all(),
        ];
        if ($profile !== null) {
            $fields = [];
            foreach (self::PROFILE_FIELDS as $field) {
                $value = $profile->getAttribute($field);
                $fields[$field] = match (true) {
                    $value instanceof \BackedEnum => $value->value,
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                    $value === null => null,
                    default => (string) $value,
                };
            }
            $data['profile'] = $fields;
            $data['preferred_roles'] = $profile->preferredRoles->pluck('job_title_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $data['project_types'] = $profile->projectTypes->map(fn ($t) => $t->project_type instanceof \BackedEnum ? $t->project_type->value : (string) $t->project_type)->sort()->values()->all();
            $data['experiences'] = $profile->experiences->map(fn ($e) => [
                (int) $e->job_title_id, $e->start_date?->format('Y-m-d'), $e->end_date?->format('Y-m-d'), (bool) $e->is_current,
            ])->sort()->values()->all();
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
