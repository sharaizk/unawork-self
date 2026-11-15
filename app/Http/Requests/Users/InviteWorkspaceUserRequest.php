<?php

namespace App\Http\Requests\Users;

use App\Services\Provisioning\ProvisionUserData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Invite a person to the owner's company workspace by email (UNA-012). New invitees are always
 * employees: no role is accepted. The inviter must own the workspace (checked in the controller,
 * which resolves the workspace from the slug).
 */
class InviteWorkspaceUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('useremail'))) {
            $this->merge(['useremail' => ProvisionUserData::normalizeEmail($this->input('useremail'))]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'useremail' => ['required', 'string', 'email', 'max:255'],
            'userpassword' => ['nullable', 'string', 'min:6', 'max:255'],
            'password_switch' => ['nullable', 'in:on,off'],
            'role' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return ['username' => __('name'), 'useremail' => __('email'), 'userpassword' => __('password')];
    }
}
