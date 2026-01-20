<?php
require 'config.php';

$headers = getallheaders();
// Header name can vary in case, so check common variants
$signature = $headers['BTCPay-Sig'] ?? $headers['Btcpay-Sig'] ?? $headers['BTCPAY-SIG'] ?? '';

$payload = file_get_contents('php://input');

// Load webhook secret from stored config (server-generated), fallback to global
$config = getConfig();
$secret = $config['webhookSecret'] ?? $webhookSecret;

// Verify Signature
$expectedSig = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if ($signature !== $expectedSig) {
    // Log invalid signature for debugging
    $log = "[" . date('Y-m-d H:i:s') . "] INVALID SIGNATURE\n";
    $log .= "  Received sig: $signature\n";
    $log .= "  Expected sig: $expectedSig\n";
    $log .= "  Secret used:  " . substr($secret, 0, 4) . "...\n";
    $log .= "  Payload:      $payload\n";
    $log .= "  Headers:      " . json_encode($headers) . "\n\n";
    file_put_contents('webhook_log.txt', $log, FILE_APPEND);

    http_response_code(403);
    die('Invalid signature');
}

$data = json_decode($payload, true);
$invoiceId = $data['invoiceId'] ?? 'unknown';
$type = $data['type'] ?? 'unknown';

// Log it to a file so you can see it working
$log = "[" . date('Y-m-d H:i:s') . "] Event: $type | Invoice: $invoiceId" . PHP_EOL;
file_put_contents('webhook_log.txt', $log, FILE_APPEND);

echo "Webhook received";