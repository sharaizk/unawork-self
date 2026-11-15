<?php

namespace App\Http\Requests\Auth;

use App\Enums\User\UserRole;
use App\Models\Utility;
use App\Services\Provisioning\ProvisionUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

/** Web self-registration (UNA-012): company or employee only; the email is normalized first. */
class RegisterUserRequest extends FormRequest
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
        $setting = Utility::getAdminPaymentSetting();

        return array_filter([
            'g-recaptcha-response' => ($setting['recaptcha_module'] ?? 'off') === 'on' ? ['required'] : null,
            'name' => ['required', 'string', 'max:255'],
            // The service re-checks case-insensitively and settles races with the unique index.
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', Rules\Password::defaults()],
            // Only the account personas offered on the form; anything else is rejected.
            'role' => ['required', Rule::in([UserRole::COMPANY->value, UserRole::EMPLOYEE->value])],
            'ref_code' => ['nullable', 'string', 'max:20'],
        ]);
    }

    public function messages(): array
    {
        return ['email.unique' => __('An account with this email address already exists. Please sign in or reset your password.')];
    }
}
