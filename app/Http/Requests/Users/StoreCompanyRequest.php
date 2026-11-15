<?php

namespace App\Http\Requests\Users;

use App\Services\Provisioning\ProvisionUserData;
use Illuminate\Foundation\Http\FormRequest;

/** Administrator "Create Company" (UNA-012). Always a company; no role is accepted. */
class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdministrator();
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
            'workspace' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password_switch' => ['nullable', 'in:on,off'],
            'password' => ['nullable', 'required_if:password_switch,on', 'string', 'min:6'],
            'role' => ['prohibited'],
        ];
    }
}
