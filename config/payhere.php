<?php

/*
 * Configuration for laravel-payhere/laravel-payhere.
 *
 * UnaWork does NOT rely on these global values for real payments. Credentials are
 * stored in the database (admin payment settings for plan purchases, workspace
 * payment settings for invoices) and applied per checkout/notification by
 * App\Services\PayHereService. These keys only provide safe defaults for the
 * package's form builder and must not hold production secrets in the repository.
 */

return [

    // Toggle between sandbox (https://sandbox.payhere.lk) and live (https://www.payhere.lk).
    'sandbox' => env('PAYHERE_SANDBOX', false),

    'merchant_id' => env('PAYHERE_MERCHANT_ID'),

    'merchant_secret' => env('PAYHERE_MERCHANT_SECRET'),

    // Supported by PayHere: LKR, USD, EUR, GBP, AUD.
    'currency' => env('PAYHERE_CURRENCY', 'LKR'),

    // Overridden per checkout with UnaWork's own routes (payhere.callback.*).
    'notify_url' => env('PAYHERE_NOTIFY_URL'),
    'return_url' => env('PAYHERE_RETURN_URL'),
    'cancel_url' => env('PAYHERE_CANCEL_URL'),

    // Only needed for PayHere's REST APIs (retrieval/refund), which UnaWork does not call.
    'app_id' => env('PAYHERE_APP_ID'),
    'app_secret' => env('PAYHERE_APP_SECRET'),

];
