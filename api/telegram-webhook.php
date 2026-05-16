<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/passwords.php';

header('Content-Type: application/json');

// Verify webhook secret
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$expectedSecret = Config::getWebhookSecret();

if (empty($expectedSecret) || strpos($requestUri, $expectedSecret) === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Read incoming Telegram update
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update || !isset($update['message'])) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$message = $update['message'];
$chatId = (string) ($message['chat']['id'] ?? '');
$text = trim($message['text'] ?? '');

// Verify sender is authorized
$allowedChatId = Config::getTelegramChatId();
if ($chatId !== $allowedChatId) {
    sendTelegramMessage($chatId, '⛔ Du bist nicht berechtigt, diesen Bot zu nutzen.');
    exit;
}

$passwordManager = new PasswordManager();

// Parse commands
if (preg_match('/^\/setpassword\s+(\S+)\s+(\S+)$/i', $text, $matches)) {
    $password = $matches[1];
    $duration = $matches[2];
    $result = $passwordManager->addPassword($password, $duration);

    if ($result['success']) {
        sendTelegramMessage($chatId, "✅ " . $result['message']);
    } else {
        sendTelegramMessage($chatId, "❌ " . $result['message']);
    }
} elseif (preg_match('/^\/listpasswords$/i', $text)) {
    $list = $passwordManager->listPasswords();

    if (empty($list)) {
        sendTelegramMessage($chatId, "📋 Keine aktiven Passwörter vorhanden.");
    } else {
        $msg = "📋 *Aktive Passwörter:*\n\n";
        foreach ($list as $entry) {
            $msg .= "#{$entry['index']} | Dauer: {$entry['duration']}\n";
            $msg .= "   Erstellt: {$entry['created']}\n";
            $msg .= "   Läuft ab: {$entry['expires']}\n";
            $msg .= "   Verbleibend: {$entry['remaining']}\n\n";
        }
        sendTelegramMessage($chatId, $msg, 'Markdown');
    }
} elseif (preg_match('/^\/deletepassword\s+(\d+)$/i', $text, $matches)) {
    $index = (int) $matches[1];
    if ($passwordManager->deleteByIndex($index)) {
        sendTelegramMessage($chatId, "🗑️ Passwort #{$index} wurde gelöscht.");
    } else {
        sendTelegramMessage($chatId, "❌ Passwort #{$index} nicht gefunden.");
    }
} elseif (preg_match('/^\/deleteall$/i', $text)) {
    $passwordManager->deleteAll();
    sendTelegramMessage($chatId, "🗑️ Alle Passwörter wurden gelöscht.");
} elseif (preg_match('/^\/help$/i', $text) || $text === '/start') {
    $help = "🤖 *File Uploader Bot*\n\n";
    $help .= "*Befehle:*\n";
    $help .= "/setpassword `<passwort>` `<dauer>` — Neues Passwort setzen\n";
    $help .= "  Dauer: z.B. `30m`, `1h`, `24h`, `7d`, `2w`\n\n";
    $help .= "/listpasswords — Alle aktiven Passwörter anzeigen\n";
    $help .= "/deletepassword `<nr>` — Passwort nach Nummer löschen\n";
    $help .= "/deleteall — Alle Passwörter löschen\n";
    $help .= "/help — Diese Hilfe anzeigen";
    sendTelegramMessage($chatId, $help, 'Markdown');
} else {
    sendTelegramMessage($chatId, "❓ Unbekannter Befehl. Nutze /help für eine Übersicht.");
}

http_response_code(200);
echo json_encode(['ok' => true]);

function sendTelegramMessage(string $chatId, string $text, string $parseMode = ''): void
{
    $token = Config::getTelegramBotToken();
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        'chat_id' => $chatId,
        'text' => $text,
    ];

    if ($parseMode !== '') {
        $data['parse_mode'] = $parseMode;
    }

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'timeout' => 10,
        ],
    ];

    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}
