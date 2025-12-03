<?php
// Production site configuration for the real domain.
// Copy this to config/site.php on your production server (keep out of VCS).
// DO NOT store secrets in this file if you prefer environment variables.

return [
    // Public URL for the application on the live server.
    // Use https:// in production and ensure you have a valid TLS certificate.
    'app_url' => 'https://file.match3onlinerewareds.gamer.gd',

    // Domain and subdomain used for this installation (exact spelling from your request).
    'domain' => 'file.match3onlinerewareds.gamer.gd',

    // Webhook URL PayPal will call. Must be reachable over HTTPS in PayPal dashboard.
    'webhook_url' => 'https://file.match3onlinerewareds.gamer.gd/api/webhook.php',

    // Admin contact (for alerts)
    'admin_email' => 'admin@file.match3onlinerewareds.gamer.gd',

    // Path where uploaded files or site assets will be stored (absolute path on server).
    'files_path' => '/var/www/file.match3onlinerewareds.gamer.gd/files',
];