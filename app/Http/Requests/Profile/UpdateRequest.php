<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'cv_file' => 'nullable|file|mimes:pdf,doc,docx,odt,txt|max:10240',
            'about' => 'nullable|string|max:25500',
            'job_title_id' => 'nullable|integer|exists:job_titles,id',
            'skill_ids' => 'nullable|array',
            'skill_ids.*' => 'integer|exists:skills,id',
        ];
    }
}
