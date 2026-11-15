<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * UNA-007 fields are additive and nullable; relation-backed lists are only included when the
     * relation was eager loaded (EmployeeService::LISTING_RELATIONS), so the resource never
     * triggers lazy loading per row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        $enum = fn ($value) => $value instanceof \BackedEnum ? $value->value : $value;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,

            'country' => optional($profile)->country,
            'state' => optional($profile)->state,
            'city' => optional($profile)->city,
            'zip_code' => optional($profile)->zip_code,
            'address' => optional($profile)->address,
            'phone_number' => optional($profile)->phone_number,
            'cv_file' => optional($profile)->cv_file,

            'skills' => $this->skills->map(function ($skill) {
                return [
                    'skill_id' => $skill->id,
                    'name' => $skill->name,
                    'proficiency' => $skill->pivot->proficiency ?? null,
                ];
            }),

            // UNA-007 structured profile
            'industry_id' => optional($profile)->industry_id,
            'industry' => $profile && $profile->relationLoaded('industry') ? optional($profile->industry)->name : null,
            'job_title_id' => optional($profile)->job_title_id,
            'job_title' => $profile && $profile->relationLoaded('jobTitle') ? optional($profile->jobTitle)->title : null,
            'years_experience' => optional($profile)->years_experience !== null ? (float) $profile->years_experience : null,
            'timezone' => optional($profile)->timezone,
            'availability_status' => $enum(optional($profile)->availability_status),
            'available_from' => optional(optional($profile)->available_from)->toDateString(),
            'hours_per_week' => optional($profile)->hours_per_week,
            'preferred_work_mode' => $enum(optional($profile)->preferred_work_mode),
            'preferred_engagement_type' => $enum(optional($profile)->preferred_engagement_type),
            'open_to_relocation' => optional($profile)->open_to_relocation,
            'willing_to_travel' => optional($profile)->willing_to_travel,
            'linkedin_url' => optional($profile)->linkedin_url,
            'github_url' => optional($profile)->github_url,
            'website' => optional($profile)->website,
            'preferred_roles' => $profile && $profile->relationLoaded('preferredRoles')
                ? $profile->preferredRoles->map(fn ($role) => ['job_title_id' => $role->job_title_id, 'title' => optional($role->jobTitle)->title])->values()
                : [],
            'project_types' => $profile && $profile->relationLoaded('projectTypes')
                ? $profile->projectTypes->map(fn ($type) => $type->project_type->value)->values()
                : [],
        ];
    }
}
