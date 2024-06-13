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
            'country' => 'string|max:255',
            'state' => 'string|max:255',
            'city' => 'string|max:255',
            'zip_code' => 'string|max:20',
            'address' => 'string|max:255',
            'phone_number' => 'string|max:20',
            'cv_file' => 'file|mimes:pdf|max:10240',
        ];
    }
}
