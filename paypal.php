<?php
// Copy this file to config/paypal.php on your server and fill in real credentials.
// Do NOT commit the real file to source control. Keep it outside the webroot if possible.

return [
    // 'env' => 'live'
    'env' => 'live',

    // REST API credentials (create an app in PayPal Developer Dashboard)
    'client_id' => 'ARRrOcdVX_N7KNHLuO_DLTWvM_QWN2imRTaGRyhlEJSdJI2j0_0xf2cqDRSxJZOT3b9g3Iv8o_I5LCko',
    'client_secret' => 'EM1hLNhpLmXS52PFnPgjQoUijFSAKRX7Lkw6QhhmrPp0KXXR0K52tdn2VNHCzNv-ZcUK-0ky1yXlCw-q',

    // Base API URLs for sandbox and live.
    'api_base' => [
        'sandbox' => 'https://api-m.sandbox.paypal.com',
        'live' => 'https://api-m.paypal.com' 'juniorfar81@outlook.com',
    ],

    // Default payout currency
    'currency' => 'USD',

    // Minimal payout amount (server-side enforce)
    'min_payout' => 1.00,

    // Token cache filename (relative to sys_get_temp_dir())
    'token_cache_file' => 'paypal_token_cache.json',

    // The webhook ID PayPal gives you when you register your webhook URL in the PayPal Dashboard.
    // Set this to the webhook id for your registered webhook (sandbox or live depending on env).
    'https://match3onlinerewards.gamer.gd' => '027476735E133874M',

curl -v -X POST "https://api-m.sandbox.paypal.com/v1/oauth2/token" \
 -u "ARRrOcdVX_N7KNHLuO_DLTWvM_QWN2imRTaGRyhlEJSdJI2j0_0xf2cqDRSxJZOT3b9g3Iv8o_I5LCko:EM1hLNhpLmXS52PFnPgjQoUijFSAKRX7Lkw6QhhmrPp0KXXR0K52tdn2VNHCzNv-ZcUK-0ky1yXlCw-q" \
 -H "Content-Type: application/x-www-form-urlencoded" \
 -d "grant_type=client_credentials"
];
