# BTCPay Server Greenfield API Test

A minimal PHP application for testing BTCPay Server's Greenfield API integration.

## Features

- OAuth authorization flow with BTCPay Server
- Invoice creation via Greenfield API
- Webhook registration and signature verification

## Requirements

- PHP 8.0+
- Composer
- A running BTCPay Server instance

## Setup

1. Install dependencies:
   ```bash
   composer install
   ```

2. Configure `config.php`:
   ```php
   $btcpayHost = 'http://localhost:8000/router.php'; // BTCPay Server URL
   $appUrl = 'http://localhost:8001';                 // This app's public URL
   $webhookSecret = 'mysupersecret';                  // Secret for webhook verification
   ```

3. Start the PHP built-in server:
   ```bash
   php -S localhost:8001
   ```

4. Open http://localhost:8001/authorize.php to pair with your BTCPay Server store.

## Using with External BTCPay Server

If your BTCPay Server is hosted externally, it needs a public URL to send webhooks. Use ngrok:

1. Start ngrok:
   ```bash
   ngrok http 8001
   ```

2. Update `config.php` with your URLs:
   ```php
   $btcpayHost = 'https://your-btcpay-server.com';
   $appUrl = 'https://abc123.ngrok.io';  // Your ngrok URL
   ```

3. Run the authorization flow - the webhook will be registered automatically.

4. Keep both the PHP server and ngrok running while testing.

## Files

- `index.php` - Store page with checkout
- `authorize.php` - OAuth authorization flow
- `webhook.php` - Webhook handler for payment events
- `config.php` - Configuration and helpers

## Support and value4value

If you like this project, I would appreciate if you contributed time, talent or treasure.

Time and talent can be used in testing it out, fixing bugs or submitting pull requests.

Treasure can be [sent back through here](https://juraj.bednar.io/en/support-me/).
