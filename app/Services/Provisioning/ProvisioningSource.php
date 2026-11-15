<?php

namespace App\Services\Provisioning;

use App\Enums\User\UserRole;

/** Where an ordinary User account is provisioned from (UNA-012). */
enum ProvisioningSource: string
{
    case WEB_REGISTRATION = 'web_registration';
    case API_REGISTRATION = 'api_registration';
    case ADMINISTRATOR_CREATED = 'administrator_created';
    case CSV_IMPORT = 'csv_import';
    case WORKSPACE_INVITATION = 'workspace_invitation';
    case POD_INVITATION = 'pod_invitation';
    case PROJECT_INVITATION = 'project_invitation';
    case FORTIFY = 'fortify';

    /** Invitations reuse a compatible existing employee; other sources refuse an existing email. */
    public function isInvitation(): bool
    {
        return in_array($this, [self::WORKSPACE_INVITATION, self::POD_INVITATION, self::PROJECT_INVITATION], true);
    }

    public function isSelfRegistration(): bool
    {
        return in_array($this, [self::WEB_REGISTRATION, self::API_REGISTRATION, self::FORTIFY], true);
    }

    /** The persona this source always creates, or null when the caller chooses (validated). */
    public function fixedRole(): ?UserRole
    {
        return match ($this) {
            self::API_REGISTRATION, self::WORKSPACE_INVITATION, self::POD_INVITATION, self::PROJECT_INVITATION => UserRole::EMPLOYEE,
            self::ADMINISTRATOR_CREATED => UserRole::COMPANY,
            default => null,
        };
    }
}
