<?php

namespace App\Actions\Fortify;

use App\Enums\User\UserRole;
use App\Models\User;
use App\Services\Provisioning\ProvisionUserData;
use App\Services\Provisioning\ProvisioningSource;
use App\Services\Provisioning\UserProvisioningService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

/**
 * Fortify registration (scaffolding; the live form is Auth\RegisteredUserController). UNA-012: it
 * can no longer create an incomplete account - it requires a company/employee role and delegates to
 * UserProvisioningService like every other path.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private UserProvisioningService $provisioning)
    {
    }

    /**
     * @param  array  $input
     * @return \App\Models\User
     */
    public function create(array $input)
    {
        $input['email'] = ProvisionUserData::normalizeEmail($input['email'] ?? '');

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in([UserRole::COMPANY->value, UserRole::EMPLOYEE->value])],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return $this->provisioning->provision(new ProvisionUserData(
            source: ProvisioningSource::FORTIFY,
            email: $input['email'],
            name: $input['name'],
            role: UserRole::from($input['role']),
            password: $input['password'],
            emailVerified: false,
        ))->user;
    }
}
