<?php

namespace App\Http\Requests\Auth;

use App\Services\Provisioning\ProvisionUserData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * API sign-up (UNA-012). The API contract creates employees only: no role is accepted, and a
 * submitted `role` is rejected rather than silently ignored.
 */
class ApiRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => ProvisionUserData::normalizeEmail($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => __('An account with this email address already exists. Please sign in or reset your password.'),
            'role.prohibited' => __('API registration always creates an employee account.'),
        ];
    }
}
