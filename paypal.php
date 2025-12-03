<?php
// Copy this file to config/paypal.php on your server and fill in real credentials.
// Do NOT commit the real file to source control. Keep it outside the webroot if possible.

return [
    // 'env' => 'sandbox' or 'live'
    'env' => 'sandbox',

    // REST API credentials (create an app in PayPal Developer Dashboard)
    'client_id' => 'YOUR_PAYPAL_CLIENT_ID_HERE',
    'client_secret' => 'YOUR_PAYPAL_CLIENT_SECRET_HERE',

    // Base API URLs for sandbox and live.
    'api_base' => [
        'sandbox' => 'https://api-m.sandbox.paypal.com',
        'live' => 'https://api-m.paypal.com',
    ],

    // Default payout currency
    'currency' => 'USD',

    // Minimal payout amount (server-side enforce)
    'min_payout' => 1.00,

    // Token cache filename (relative to sys_get_temp_dir())
    'token_cache_file' => 'paypal_token_cache.json',

    // The webhook ID PayPal gives you when you register your webhook URL in the PayPal Dashboard.
    // Set this to the webhook id for your registered webhook (sandbox or live depending on env).
    'webhook_id' => 'YOUR_PAYPAL_WEBHOOK_ID_HERE',
];