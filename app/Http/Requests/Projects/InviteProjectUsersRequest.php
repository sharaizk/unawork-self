<?php

namespace App\Http\Requests\Projects;

use App\Services\Provisioning\ProvisionUserData;
use Illuminate\Foundation\Http\FormRequest;

/** Invite people to a project by email (UNA-012). New accounts are employees; no role is accepted. */
class InviteProjectUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $list = $this->input('users_list');
        if (is_array($list)) {
            $this->merge(['users_list' => array_values(array_unique(array_map(fn ($e) => ProvisionUserData::normalizeEmail((string) $e), $list)))]);
        }
    }

    public function rules(): array
    {
        return [
            'users_list' => ['required', 'array', 'min:1', 'max:50'],
            'users_list.*' => ['required', 'string', 'email', 'max:255'],
            'role' => ['prohibited'],
        ];
    }
}
