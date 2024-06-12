<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,

            'country' => optional($this->profile)->country,
            'state' => optional($this->profile)->state,
            'city' => optional($this->profile)->city,
            'zip_code' => optional($this->profile)->zip_code,
            'address' => optional($this->profile)->address,
            'phone_number' => optional($this->profile)->phone_number,
            'cv_file' => optional($this->profile)->cv_file,
            
            'skills' => $this->skills->map(function ($skill) {
                return [
                    'skill_id' => $skill->id,
                    'name' => $skill->name,
                ];
            }),
        ];
    }
}
