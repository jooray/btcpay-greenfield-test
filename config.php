<?php
require 'vendor/autoload.php';

session_start();

// Suppress deprecation warnings from vendor libraries
error_reporting(E_ALL & ~E_DEPRECATED);

// CONFIGURATION
$btcpayHost = 'http://localhost:8000/router.php'; // BTCPay Server URL
$appUrl = 'http://localhost:8001';                 // This app's public URL (use ngrok URL if BTCPay is external)
$webhookSecret = 'mysupersecret';                  // Secret for webhook signature verification

// HELPER: Simulating a database using a JSON file
function saveConfig($apiKey, $storeId, $host = null, $webhookSecret = null) {
    global $btcpayHost;
    file_put_contents('store_config.json', json_encode([
        'apiKey' => $apiKey,
        'storeId' => $storeId,
        'btcpayHost' => $host ?? $btcpayHost,
        'webhookSecret' => $webhookSecret
    ]));
}

function getConfig() {
    if (!file_exists('store_config.json')) return null;
    return json_decode(file_get_contents('store_config.json'), true);
}

function removeConfig() {
    if (file_exists('store_config.json')) {
        unlink('store_config.json');
    }
}
