<?php
// PayPal webhook receiver: verifies signature and processes payout item events.
// Place this endpoint at a publicly accessible URL and register it in PayPal dashboard as a webhook.
// NOTE: Use HTTPS and protect other API routes; this handler is public (PayPal posts to it).

require_once __DIR__ . '/paypal_client.php';

// Simple helpers
function getRequestHeadersLower() {
    // getallheaders may not be available in some FPM setups; fallback using $_SERVER
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        $out = [];
        foreach ($h as $k => $v) { $out[$k] = $v; }
        return $out;
    }
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $nameKey = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$nameKey] = $value;
        }
    }
    return $headers;
}

function logWebhook($line) {
    $logFile = __DIR__ . '/webhook.log';
    $ts = date('c');
    @file_put_contents($logFile, "[$ts] " . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function ensureDataDir() {
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    return $dataDir;
}

// Read incoming raw body
$rawBody = file_get_contents('php://input');
if ($rawBody === false) {
    http_response_code(400);
    echo 'No body';
    exit;
}

$headers = getRequestHeadersLower();

try {
    $verifyResp = paypal_verify_webhook_signature($headers, $rawBody);
} catch (Exception $e) {
    logWebhook("Verification call failed: " . $e->getMessage());
    http_response_code(500);
    echo 'Verification call failed';
    exit;
}

// verification response contains 'verification_status' field (e.g., SUCCESS)
$verificationStatus = $verifyResp['verification_status'] ?? '';
logWebhook("Verification status: " . json_encode($verifyResp));

if ($verificationStatus !== 'SUCCESS') {
    // Not verified
    logWebhook("Webhook verification failed. Raw payload: " . $rawBody);
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

// Parse event JSON
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    logWebhook("Invalid JSON payload after verification");
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

// Log event original
logWebhook("Received verified event: " . ($payload['event_type'] ?? 'unknown'));

// Basic processing for payout item events
$eventType = $payload['event_type'] ?? '';
$resource = $payload['resource'] ?? null;

// Keep a small JSON file mapping payout_item_id -> latest status (demo only)
$dataDir = ensureDataDir();
$payoutsFile = $dataDir . '/payouts.json';
$payouts = [];
if (file_exists($payoutsFile)) {
    $raw = @file_get_contents($payoutsFile);
    $payouts = $raw ? json_decode($raw, true) : [];
    if (!is_array($payouts)) $payouts = [];
}

switch ($eventType) {
    case 'PAYMENT.PAYOUTS-ITEM.SUCCEEDED':
        // resource contains payout_item_id and transaction_id, payout_item, transaction_status
        $itemId = $resource['payout_item_id'] ?? ($resource['sender_item_id'] ?? null);
        $status = $resource['transaction_status'] ?? 'SUCCESS';
        $txnId = $resource['transaction_id'] ?? null;
        // Update demo store
        if ($itemId) {
            $payouts[$itemId] = [
                'status' => $status,
                'transaction_id' => $txnId,
                'event' => $eventType,
                'resource' => $resource,
                'updated_at' => date('c')
            ];
            @file_put_contents($payoutsFile, json_encode($payouts, JSON_PRETTY_PRINT));
        }
        logWebhook("Payout succeeded: item={$itemId} txn={$txnId}");
        break;

    case 'PAYMENT.PAYOUTS-ITEM.FAILED':
        $itemId = $resource['payout_item_id'] ?? ($resource['sender_item_id'] ?? null);
        $status = $resource['transaction_status'] ?? 'FAILED';
        $errors = $resource['errors'] ?? null;
        if ($itemId) {
            $payouts[$itemId] = [
                'status' => $status,
                'errors' => $errors,
                'event' => $eventType,
                'resource' => $resource,
                'updated_at' => date('c')
            ];
            @file_put_contents($payoutsFile, json_encode($payouts, JSON_PRETTY_PRINT));
        }
        logWebhook("Payout failed: item={$itemId} errors=" . json_encode($errors));
        break;

    case 'PAYMENT.PAYOUTS-ITEM.UNCLAIMED':
        $itemId = $resource['payout_item_id'] ?? ($resource['sender_item_id'] ?? null);
        if ($itemId) {
            $payouts[$itemId] = [
                'status' => 'UNCLAIMED',
                'event' => $eventType,
                'resource' => $resource,
                'updated_at' => date('c')
            ];
            @file_put_contents($payoutsFile, json_encode($payouts, JSON_PRETTY_PRINT));
        }
        logWebhook("Payout unclaimed: item={$itemId}");
        break;

    case 'PAYMENT.PAYOUTS-ITEM.REFUNDED':
        $itemId = $resource['payout_item_id'] ?? ($resource['sender_item_id'] ?? null);
        if ($itemId) {
            $payouts[$itemId] = [
                'status' => 'REFUNDED',
                'event' => $eventType,
                'resource' => $resource,
                'updated_at' => date('c')
            ];
            @file_put_contents($payoutsFile, json_encode($payouts, JSON_PRETTY_PRINT));
        }
        logWebhook("Payout refunded: item={$itemId}");
        break;

    default:
        // For other events, just log them; you can add handlers for other types
        logWebhook("Unhandled event type: {$eventType}");
        break;
}

// Respond 200 to acknowledge
http_response_code(200);
echo 'OK';