<?php

namespace App\Services\Provisioning;

use App\Enums\User\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Administrator CSV import of company/employee accounts (UNA-012).
 *
 * Columns: name, email, password, account type ("company"/"employee"; blank or the legacy "user"
 * means company). The whole file is validated first - emails normalized, roles normalized, duplicate
 * emails inside the file and administrator emails rejected - and nothing is written if any row is
 * invalid. Accounts that already exist are skipped and reported, never overwritten. All new accounts
 * are created through UserProvisioningService::apply() in one transaction (all or nothing).
 */
class CsvAccountImporter
{
    public function __construct(private UserProvisioningService $provisioning)
    {
    }

    /**
     * @param array<int, array<int, mixed>> $rows rows including the header row (index 0)
     * @return array{created:string[], skipped:string[]}
     * @throws ValidationException listing every invalid row; nothing written
     */
    public function import(array $rows): array
    {
        $errors = [];
        $valid = [];
        $seen = [];

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $line = $index + 1;
            if (!array_filter((array) $row, fn ($v) => trim((string) $v) !== '')) {
                continue; // blank line
            }
            $email = ProvisionUserData::normalizeEmail($row[1] ?? '');
            $role = self::role($row[3] ?? null);

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                $errors[] = __('Row :row: invalid email address.', ['row' => $line]);
                continue;
            }
            if ($role === null) {
                $errors[] = __('Row :row: invalid account type ":type". Use "company" or "employee".', ['row' => $line, 'type' => (string) ($row[3] ?? '')]);
                continue;
            }
            if (isset($seen[$email])) {
                $errors[] = __('Row :row: :email also appears on row :first.', ['row' => $line, 'email' => $email, 'first' => $seen[$email]]);
                continue;
            }
            $seen[$email] = $line;
            $valid[] = ['email' => $email, 'name' => trim((string) ($row[0] ?? '')), 'password' => trim((string) ($row[2] ?? '')), 'role' => $role];
        }

        $existing = $valid
            ? User::whereIn(DB::raw('LOWER(email)'), array_column($valid, 'email'))->get()->keyBy(fn (User $u) => mb_strtolower($u->email))
            : collect();
        foreach ($valid as $row) {
            if (($user = $existing->get($row['email'])) && $user->isAdministrator()) {
                $errors[] = __('Row :row: administrator accounts cannot be changed by import.', ['row' => $seen[$row['email']]]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        $report = ['created' => [], 'skipped' => []];
        DB::transaction(function () use ($valid, $existing, &$report) {
            $report = ['created' => [], 'skipped' => []];
            foreach ($valid as $row) {
                if ($existing->has($row['email'])) {
                    $report['skipped'][] = $row['email']; // never overwrite an existing account
                    continue;
                }
                $this->provisioning->apply(new ProvisionUserData(
                    source: ProvisioningSource::CSV_IMPORT,
                    email: $row['email'],
                    name: $row['name'] ?: null,
                    role: $row['role'],
                    password: $row['password'] !== '' ? $row['password'] : null,
                ));
                $report['created'][] = $row['email'];
            }
        }, UserProvisioningService::ATTEMPTS);

        return $report;
    }

    /** Normalize the account-type column; null means invalid/ambiguous. */
    public static function role($value): ?UserRole
    {
        return match (strtolower(trim((string) $value))) {
            '', 'user', UserRole::COMPANY->value => UserRole::COMPANY,
            UserRole::EMPLOYEE->value => UserRole::EMPLOYEE,
            default => null,
        };
    }
}
