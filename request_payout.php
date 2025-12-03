<?php
// PayPal REST Payouts example (demo).
// IMPORTANT: This is demo code. In production, you MUST:
// - Authenticate the caller (session/JWT) and check user balance server-side.
// - Debit the user's balance BEFORE issuing payouts (or use a pending/approval workflow).
// - Record payout attempts and associate them with user/account IDs.
// - Add logging, retries, idempotency management, and CSRF protections.
// - Use HTTPS and protect your config file.

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$recipientEmail = isset($body['paypalEmail']) ? trim($body['paypalEmail']) : '';
$amount = isset($body['amount']) ? floatval($body['amount']) : 0.0;

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid recipient or amount']);
    exit;
}

// Load config (ensure config/paypal.php exists on your server)
$configPath = __DIR__ . '/../config/paypal.php';
if (!file_exists($configPath)) {
    // For development convenience fallback to example, but in production this must be real
    $configPath = __DIR__ . '/../config/paypal.example.php';
}
$config = include $configPath;

$env = $config['env'] ?? 'sandbox';
$apiBase = $config['api_base'][$env] ?? $config['api_base']['sandbox'];
$clientId = $config['client_id'] ?? '';
$clientSecret = $config['client_secret'] ?? '';
$currency = $config['currency'] ?? 'USD';
$minPayout = $config['min_payout'] ?? 1.00;

if ($amount < $minPayout) {
    echo json_encode(['success' => false, 'message' => "Minimum payout is {$minPayout} {$currency}"]);
    exit;
}

// TODO: Authenticate the caller and check user's balance server-side before proceeding.

// Helper: get OAuth2 access token (with simple file cache)
function getAccessToken($clientId, $clientSecret, $apiBase, $cacheFileName) {
    $cachePath = sys_get_temp_dir() . '/' . $cacheFileName;
    // load cache if valid
    if (file_exists($cachePath)) {
        $raw = @file_get_contents($cachePath);
        if ($raw) {
            $data = json_decode($raw, true);
            if ($data && isset($data['expires_at']) && $data['expires_at'] > time() + 10) {
                return $data['access_token'];
            }
        }
    }

    $tokenUrl = rtrim($apiBase, '/') . '/v1/oauth2/token';
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

    // cache token (expires_in is seconds)
    $expiresIn = isset($arr['expires_in']) ? intval($arr['expires_in']) : 3200;
    $cacheData = [
        'access_token' => $arr['access_token'],
        'expires_at' => time() + $expiresIn,
    ];
    @file_put_contents($cachePath, json_encode($cacheData, JSON_PRETTY_PRINT));

    return $arr['access_token'];
}

try {
    $accessToken = getAccessToken($clientId, $clientSecret, $apiBase, $config['token_cache_file'] ?? 'paypal_token_cache.json');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to obtain PayPal access token: ' . $e->getMessage()]);
    exit;
}

// Build Payouts request body (single receiver)
$senderBatchId = uniqid('batch_', true);
$senderItemId = uniqid('item_', true);

// Convert amount to 2 decimals
$amountValue = number_format($amount, 2, '.', '');

$payoutBody = [
    'sender_batch_header' => [
        'sender_batch_id' => $senderBatchId,
        'email_subject' => 'You have a payout!',
        'email_message' => 'You have received a payout from Match3 Rewards.',
    ],
    'items' => [
        [
            'recipient_type' => 'EMAIL',
            'amount' => [
                'value' => $amountValue,
                'currency' => $currency
            ],
            'receiver' => $recipientEmail,
            'note' => 'Match3 payout',
            'sender_item_id' => $senderItemId
        ]
    ]
];

$payoutUrl = rtrim($apiBase, '/') . '/v1/payments/payouts';
$ch = curl_init($payoutUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payoutBody));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken,
    // Make request idempotent using PayPal-Request-Id (server-side should manage this id)
    'PayPal-Request-Id: ' . uniqid('req_', true)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'message' => 'cURL error sending payout: ' . $curlErr]);
    exit;
}

$respArr = json_decode($response, true);

// Basic success check: HTTP 201 Created (payout created) or 200
if ($httpCode >= 200 && $httpCode < 300 && is_array($respArr)) {
    // IMPORTANT: persist payout record in DB with batch ID, sender_item_id, user id, amount, paypal receiver, and initial PayPal status.
    echo json_encode(['success' => true, 'message' => 'Payout created', 'paypal_response' => $respArr]);
    exit;
} else {
    // Return error details
    $errMsg = is_array($respArr) ? json_encode($respArr) : $response;
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'PayPal error: ' . $errMsg, 'http_code' => $httpCode]);
    exit;
}