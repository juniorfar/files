<?php
// Example server-to-server call to Python microservice
function call_python_payout($paypalEmail, $amount, $note = '') {
    // Prefer internal call to 127.0.0.1 to avoid external network
    $url = 'http://127.0.0.1:5000/payout';
    // OR use HTTPS via nginx: $url = 'https://file.match3onlinerewareds.gamer.gd/payout';

    $payload = json_encode([
        'paypalEmail' => $paypalEmail,
        'amount' => $amount,
        'note' => $note
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        // Use the same PY_MS_API_KEY you stored in the server .env file
        'X-Api-Key: ' . getenv('PY_MS_API_KEY'),
        'Content-Length: ' . strlen($payload)
    ]);
    // If calling via https and your server uses self-signed certs adjust this; normally don't disable verify in prod
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception("cURL error: " . $err);
    }

    return ['http_code' => $code, 'body' => json_decode($resp, true)];
}