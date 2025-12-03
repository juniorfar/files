<?php
// Helper functions for PayPal REST integration: token caching and webhook signature verification.
// Include this from other API endpoints: require_once __DIR__ . '/paypal_client.php';

function loadPaypalConfig() {
    $cfgPath = __DIR__ . '/../config/paypal.php';
    if (!file_exists($cfgPath)) {
        $cfgPath = __DIR__ . '/../config/paypal.example.php';
    }
    $cfg = include $cfgPath;
    return $cfg;
}

/**
 * Obtain an OAuth2 access token using client credentials.
 * Uses a simple file cache under sys_get_temp_dir().
 *
 * @return string access token
 * @throws Exception on failure
 */
function paypal_get_access_token() {
    $config = loadPaypalConfig();
    $clientId = $config['client_id'] ?? '';
    $clientSecret = $config['client_secret'] ?? '';
    $apiBase = rtrim($config['api_base'][$config['env'] ?? 'sandbox'], '/');
    $cacheFile = $config['token_cache_file'] ?? 'paypal_token_cache.json';
    $cachePath = sys_get_temp_dir() . '/' . $cacheFile;

    // return cached token if still valid
    if (file_exists($cachePath)) {
        $raw = @file_get_contents($cachePath);
        if ($raw) {
            $data = json_decode($raw, true);
            if ($data && isset($data['access_token']) && isset($data['expires_at']) && $data['expires_at'] > time() + 10) {
                return $data['access_token'];
            }
        }
    }

    $tokenUrl = $apiBase . '/v1/oauth2/token';
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception("cURL error getting token: " . $curlErr);
    }

    $arr = json_decode($resp, true);
    if (!is_array($arr) || !isset($arr['access_token'])) {
        throw new Exception("Invalid token response: HTTP {$httpCode} - {$resp}");
    }

    $expiresIn = isset($arr['expires_in']) ? intval($arr['expires_in']) : 3200;
    $cacheData = [
        'access_token' => $arr['access_token'],
        'expires_at' => time() + $expiresIn,
    ];
    @file_put_contents($cachePath, json_encode($cacheData, JSON_PRETTY_PRINT));

    return $arr['access_token'];
}

/**
 * Verify PayPal webhook signature by calling PayPal's verify-webhook-signature endpoint.
 *
 * @param array $headers associative array of headers (lower or original case ok)
 * @param string $rawBody raw JSON body string
 * @return array verification response as array (contains 'verification_status' typically)
 * @throws Exception on network or unexpected failures
 */
function paypal_verify_webhook_signature($headers, $rawBody) {
    $config = loadPaypalConfig();
    $apiBase = rtrim($config['api_base'][$config['env'] ?? 'sandbox'], '/');
    $webhookId = $config['webhook_id'] ?? '';

    // Extract required PayPal headers
    // Accept both header names with dashes and underscores; prefer the HTTP_* server variables mapping.
    $transmissionId = $headers['PayPal-Transmission-Id'] ?? $headers['PAYPAL-TRANSMISSION-ID'] ?? $headers['paypal-transmission-id'] ?? $headers['HTTP_PAYPAL_TRANSMISSION_ID'] ?? null;
    $transmissionTime = $headers['PayPal-Transmission-Time'] ?? $headers['PAYPAL-TRANSMISSION-TIME'] ?? $headers['paypal-transmission-time'] ?? $headers['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? null;
    $transmissionSig = $headers['PayPal-Transmission-Sig'] ?? $headers['PAYPAL-TRANSMISSION-SIG'] ?? $headers['paypal-transmission-sig'] ?? $headers['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? null;
    $certUrl = $headers['PayPal-Cert-Url'] ?? $headers['PAYPAL-CERT-URL'] ?? $headers['paypal-cert-url'] ?? $headers['HTTP_PAYPAL_CERT_URL'] ?? null;
    $authAlgo = $headers['PayPal-Auth-Algo'] ?? $headers['PAYPAL-AUTH-ALGO'] ?? $headers['paypal-auth-algo'] ?? $headers['HTTP_PAYPAL_AUTH_ALGO'] ?? null;

    if (!$transmissionId || !$transmissionTime || !$transmissionSig || !$certUrl || !$authAlgo || !$webhookId) {
        throw new Exception("Missing required PayPal webhook headers or webhook id not configured.");
    }

    // Build verify request payload
    $verifyPayload = [
        'transmission_id' => $transmissionId,
        'transmission_time' => $transmissionTime,
        'cert_url' => $certUrl,
        'auth_algo' => $authAlgo,
        'transmission_sig' => $transmissionSig,
        'webhook_id' => $webhookId,
        // webhook_event should be the JSON decoded object
        'webhook_event' => json_decode($rawBody, true)
    ];

    $token = paypal_get_access_token();
    $url = $apiBase . '/v1/notifications/verify-webhook-signature';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verifyPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception("cURL error verifying webhook signature: " . $curlErr);
    }

    $arr = json_decode($resp, true);
    if (!is_array($arr)) {
        throw new Exception("Invalid verify response: HTTP {$httpCode} - {$resp}");
    }

    return $arr;
}