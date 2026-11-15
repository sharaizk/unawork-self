<?php

namespace App\Services\Provisioning;

use App\Enums\User\UserRole;

/**
 * Input for UserProvisioningService (UNA-012). The email is normalized (trimmed, lower-cased) on
 * construction. $password is the plaintext the caller received (registration, administrator form,
 * CSV); $generatePassword asks the service to create one instead. Neither is ever stored on the
 * model in plaintext.
 */
final class ProvisionUserData
{
    public readonly string $email;

    public function __construct(
        public readonly ProvisioningSource $source,
        string $email,
        public readonly ?string $name = null,
        public readonly ?UserRole $role = null,
        public readonly ?string $password = null,
        public readonly bool $generatePassword = false,
        public readonly bool $emailVerified = true,
        public readonly bool $canLogin = true,
        public readonly ?string $locale = null,
        public readonly ?string $workspaceName = null,
        public readonly ?string $referralUsed = null,
        // Email the login details (with the temporary/assigned password) after commit.
        public readonly bool $sendLoginDetails = false,
    ) {
        $this->email = self::normalizeEmail($email);
    }

    public static function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
