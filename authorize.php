<?php
require 'config.php';

// Handle remove pairing request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    removeConfig();
    header('Location: authorize.php');
    exit;
}

// Handle start pairing request (form submission with btcpay_host)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pair') {
    $host = trim($_POST['btcpay_host'] ?? $btcpayHost);
    $_SESSION['pairing_host'] = $host;

    $permissions = [
        'btcpay.store.cancreateinvoice',
        'btcpay.store.canviewinvoices',
        'btcpay.store.webhooks.canmodifywebhooks',
        'btcpay.store.canviewstoresettings'
    ];

    $url = \BTCPayServer\Client\ApiKey::getAuthorizeUrl(
        $host,
        $permissions,
        'My PHP Webstore',
        true,
        true,
        $appUrl . '/authorize.php',
        null
    );

    header('Location: ' . $url);
    exit;
}

// Handle callback from BTCPay (POST with apiKey)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Try to get apiKey from POST form data if JSON parsing failed
    if (!isset($data['apiKey']) && isset($_POST['apiKey'])) {
        $data = $_POST;
    }

    if (isset($data['apiKey'])) {
        // Get the host used during pairing
        $host = $_SESSION['pairing_host'] ?? $btcpayHost;

        // Check if storeId is provided directly
        $storeId = $data['storeId'] ?? null;

        // Fallback: extract storeId from permissions (format: "permission:storeId")
        if (!$storeId) {
            $permissions = $data['permissions'] ?? [];
            if (is_string($permissions)) {
                $permissions = json_decode($permissions, true) ?? [];
            }
            foreach ($permissions as $permission) {
                if (str_contains($permission, ':')) {
                    $parts = explode(':', $permission);
                    $storeId = $parts[1];
                    break;
                }
            }
        }

        if (!$storeId) {
            echo "<pre>Debug - Received data:\n" . print_r($data, true) . "</pre>";
            echo "Error: Could not determine store ID.";
            exit;
        }

        // Delete existing webhooks for this store before creating a new one
        $webhookUrl = $appUrl . '/webhook.php';
        try {
            $client = new \BTCPayServer\Client\Webhook($host, $data['apiKey']);
            $existingWebhooks = $client->getStoreWebhooks($storeId);
            foreach ($existingWebhooks->all() as $existingWebhook) {
                // Delete webhooks pointing to our URL
                if ($existingWebhook->getUrl() === $webhookUrl) {
                    $client->deleteWebhook($storeId, $existingWebhook->getId());
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors when listing/deleting - we'll try to create anyway
        }

        // Create webhook and extract server-generated secret
        $serverWebhookSecret = null;
        $webhookSuccess = false;
        try {
            $client = new \BTCPayServer\Client\Webhook($host, $data['apiKey']);
            // Pass null for secret to let server generate it
            $webhook = $client->createWebhook($storeId, $webhookUrl, null, null);
            $serverWebhookSecret = $webhook->getSecret();
            $webhookSuccess = true;
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '(201)')) {
                // Server returned 201 with webhook data in error message
                // Extract JSON from error message to get the secret
                if (preg_match('/\{.*\}/s', $e->getMessage(), $matches)) {
                    $webhookData = json_decode($matches[0], true);
                    $serverWebhookSecret = $webhookData['secret'] ?? null;
                }
                $webhookSuccess = true;
            } else {
                echo "Error registering webhook: " . $e->getMessage();
                exit;
            }
        }

        // Save config with the server-returned webhook secret
        saveConfig($data['apiKey'], $storeId, $host, $serverWebhookSecret);

        if ($webhookSuccess) {
            echo "<h1>Success! Store connected & Webhook registered.</h1><a href='index.php'>Go to Store</a>";
        }
        exit;
    }
}

// Display page
$config = getConfig();
?>
<!DOCTYPE html>
<html>
<head>
    <title>BTCPay Server Connection</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-green { background: #51b13e; color: white; }
        .btn-red { background: #d9534f; color: white; }
        .status { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .status p { margin: 5px 0; }
        input[type="text"] { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        label { font-weight: bold; }
    </style>
</head>
<body>
    <h1>BTCPay Server Connection</h1>

    <?php if ($config): ?>
        <div class="status">
            <p><strong>Status:</strong> Connected</p>
            <p><strong>Store ID:</strong> <?= htmlspecialchars($config['storeId']) ?></p>
            <p><strong>BTCPay Host:</strong> <?= htmlspecialchars($config['btcpayHost'] ?? 'N/A') ?></p>
            <p><strong>API Key:</strong> <?= htmlspecialchars(substr($config['apiKey'], 0, 8)) ?>...</p>
        </div>
        <p>
            <a href="index.php" class="btn btn-green">Go to Store</a>
        </p>
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="action" value="remove">
            <button type="submit" class="btn btn-red" onclick="return confirm('Remove pairing? You will need to re-authorize.')">Remove Pairing</button>
        </form>
    <?php else: ?>
        <p>Connect your BTCPay Server store to start accepting payments.</p>
        <form method="POST">
            <input type="hidden" name="action" value="pair">
            <label for="btcpay_host">BTCPay Server URL:</label>
            <input type="text" name="btcpay_host" id="btcpay_host" value="<?= htmlspecialchars($btcpayHost) ?>" placeholder="https://your-btcpay-server.com">
            <p>
                <button type="submit" class="btn btn-green">Authorize App</button>
            </p>
        </form>
    <?php endif; ?>
</body>
</html>
