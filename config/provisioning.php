<?php

/*
 * Account provisioning defaults (UNA-012). Read only through config(); never env() in code.
 * Used by App\Services\Provisioning\UserProvisioningService for every ordinary company/employee
 * account (registration, API, administrator, CSV import and invitations).
 */

return [

    // Plan every new account starts on (the free plan). Must exist, or provisioning fails cleanly.
    'default_plan_id' => (int) env('PROVISIONING_DEFAULT_PLAN_ID', 1),

    // Locale when the caller does not supply one (registration passes the admin default language).
    'default_locale' => env('PROVISIONING_DEFAULT_LOCALE', 'en'),

    // Owned-workspace defaults.
    'workspace_currency_code' => 'USD',
    'workspace_paypal_mode' => 'sandbox',

    // Length of passwords generated for invited accounts (sent once, after commit).
    'generated_password_length' => 12,

];
