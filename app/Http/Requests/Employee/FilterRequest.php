<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class FilterRequest extends FormRequest
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
            'name' => 'string|max:255',
            'email' => 'string|max:255',
            'country' => 'string|max:255',
            'state' => 'string|max:255',
            'city' => 'string|max:255',
            'zip_code' => 'string|max:10',
            'address' => 'string|max:255',
            'phone_number' => 'string|max:20',
            'skill_id' => 'nullable|array',
            'skill_id.*' => 'integer|exists:skills,id',
        ];
    }
}
