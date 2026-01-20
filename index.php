<?php
require 'config.php';

$config = getConfig();

if (!$config) {
    echo "Store not connected. <a href='authorize.php'>Pair now</a>";
    exit;
}

$products = [
    'sats' => ['name' => 'Test Item (100 sats)', 'amount' => '100', 'currency' => 'SATS'],
    'eur' => ['name' => 'Test Item (0.05 EUR)', 'amount' => '0.05', 'currency' => 'EUR'],
    'usd' => ['name' => 'Test Item (0.05 USD)', 'amount' => '0.05', 'currency' => 'USD'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product'])) {
    $productKey = $_POST['product'];
    if (!isset($products[$productKey])) {
        echo "Invalid product";
        exit;
    }

    $product = $products[$productKey];
    $host = $config['btcpayHost'] ?? $btcpayHost;

    try {
        $client = new \BTCPayServer\Client\Invoice($host, $config['apiKey']);

        $amount = \BTCPayServer\Util\PreciseNumber::parseString($product['amount']);
        $checkoutOptions = \BTCPayServer\Client\InvoiceCheckoutOptions::create(
            null, // speedPolicy
            null, // paymentMethods
            null, // expirationMinutes
            null, // monitoringMinutes
            null, // paymentTolerance
            $appUrl . '/', // redirectURL
            true, // redirectAutomatically
            null  // defaultLanguage
        );
        $invoice = $client->createInvoice(
            $config['storeId'],
            $product['currency'],
            $amount,
            'order-' . rand(1000, 9999),
            null, // buyerEmail
            null, // metadata
            $checkoutOptions
        );

        header("Location: " . $invoice->getCheckoutLink());
        exit;
    } catch (\Throwable $e) {
        // Library bug: throws on HTTP 201 (Created) which is actually success
        if (str_contains($e->getMessage(), '(201)')) {
            if (preg_match('/\{.*\}/s', $e->getMessage(), $matches)) {
                $data = json_decode($matches[0], true);
                if (isset($data['checkoutLink'])) {
                    header("Location: " . $data['checkoutLink']);
                    exit;
                }
            }
        }
        echo "Error creating invoice: " . $e->getMessage();
    }
}

// Read webhook log
$webhookLog = '';
if (file_exists('webhook_log.txt')) {
    $webhookLog = file_get_contents('webhook_log.txt');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Webstore</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; }
        .product { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 10px 20px; background: #51b13e; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .webhook-log { background: #1a1a1a; color: #0f0; padding: 15px; border-radius: 5px; margin-top: 30px; font-family: monospace; font-size: 12px; white-space: pre-wrap; max-height: 300px; overflow-y: auto; }
        .webhook-log-empty { color: #666; }
        h2 { margin-top: 30px; }
        a { color: #51b13e; }
    </style>
</head>
<body>
    <h1>My Minimalistic Webstore</h1>
    <p><a href="authorize.php">Manage Connection</a></p>

    <?php foreach ($products as $key => $product): ?>
    <div class="product">
        <span><?= htmlspecialchars($product['name']) ?></span>
        <form method="post" style="margin: 0;">
            <input type="hidden" name="product" value="<?= $key ?>">
            <button type="submit" class="btn">Pay with BTCPay</button>
        </form>
    </div>
    <?php endforeach; ?>

    <h2>Webhook Log</h2>
    <div class="webhook-log">
<?php if ($webhookLog): ?>
<?= htmlspecialchars($webhookLog) ?>
<?php else: ?>
<span class="webhook-log-empty">No webhook events received yet.</span>
<?php endif; ?>
    </div>
</body>
</html>
