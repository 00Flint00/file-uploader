<?php

/**
 * Run this script ONCE to register the Telegram webhook.
 * Usage: php setup-webhook.php
 *
 * Or visit: https://yourdomain.com/api/setup-webhook.php?run=1
 * (Delete this file after setup for security!)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

// Only allow CLI or with explicit ?run=1 parameter
if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
    http_response_code(403);
    echo 'Access denied. Add ?run=1 to the URL to execute.';
    exit;
}

$token = Config::getTelegramBotToken();
$secret = Config::getWebhookSecret();

if (empty($token) || $token === 'your_bot_token_here') {
    echo "ERROR: Please set TELEGRAM_BOT_TOKEN in .env first.\n";
    exit(1);
}

if (empty($secret) || $secret === 'your_random_secret_here') {
    echo "ERROR: Please set WEBHOOK_SECRET in .env first.\n";
    exit(1);
}

// Auto-detect domain
$domain = '';
if (php_sapi_name() !== 'cli') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $domain = "{$protocol}://{$host}";
} else {
    // CLI mode: ask for domain
    if ($argc < 2) {
        echo "Usage: php setup-webhook.php https://yourdomain.com\n";
        exit(1);
    }
    $domain = rtrim($argv[1], '/');
}

$webhookUrl = "{$domain}/api/telegram-webhook.php?secret={$secret}";

echo "Setting webhook to: {$webhookUrl}\n";

$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook";
$data = [
    'url' => $webhookUrl,
    'allowed_updates' => ['message'],
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($data),
        'timeout' => 15,
    ],
];

$context = stream_context_create($options);
$response = file_get_contents($apiUrl, false, $context);

if ($response === false) {
    echo "ERROR: Failed to connect to Telegram API.\n";
    exit(1);
}

$result = json_decode($response, true);

if ($result['ok'] ?? false) {
    echo "SUCCESS: Webhook registered!\n";
    echo "Description: " . ($result['description'] ?? 'OK') . "\n";
    echo "\nIMPORTANT: Delete this file (setup-webhook.php) for security!\n";
} else {
    echo "ERROR: " . ($result['description'] ?? 'Unknown error') . "\n";
    exit(1);
}
