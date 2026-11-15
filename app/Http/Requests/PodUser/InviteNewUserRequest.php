<?php

namespace App\Http\Requests\PodUser;

use App\Models\Pod;
use Illuminate\Foundation\Http\FormRequest;

class InviteNewUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pod = Pod::findOrFail($this->route('id'));

        return $this->user()->can('manageMembers', $pod);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => \App\Services\Provisioning\ProvisionUserData::normalizeEmail($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        // UNA-012: an existing compatible employee is reused, so the email need not be new; new
        // accounts are always employees (no role accepted). Without a password one is generated
        // and emailed after commit.
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'role' => ['prohibited'],
        ];
    }
}

