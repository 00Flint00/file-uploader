<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verify upload token
session_start();
$token = $_POST['upload_token'] ?? $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
$sessionToken = $_SESSION['upload_token'] ?? '';
$tokenExpires = $_SESSION['upload_token_expires'] ?? 0;

if (empty($token) || $token !== $sessionToken || time() > $tokenExpires) {
    http_response_code(401);
    echo json_encode(['error' => 'Ungültiger oder abgelaufener Upload-Token. Bitte erneut anmelden.']);
    exit;
}

// Check if files were uploaded
if (empty($_FILES['files'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Keine Dateien ausgewählt']);
    exit;
}

$uploadDir = Config::getUploadDir();
$maxFileSize = Config::getMaxFileSize();

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0750, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Upload-Verzeichnis konnte nicht erstellt werden']);
        exit;
    }
}

$results = [];
$files = normalizeFiles($_FILES['files']);

foreach ($files as $file) {
    $result = processUpload($file, $uploadDir, $maxFileSize);
    $results[] = $result;
}

$successCount = count(array_filter($results, fn(array $r): bool => $r['success']));
$failCount = count($results) - $successCount;

echo json_encode([
    'success' => $failCount === 0,
    'message' => "{$successCount} Datei(en) erfolgreich hochgeladen" . ($failCount > 0 ? ", {$failCount} fehlgeschlagen" : ''),
    'results' => $results,
]);

function processUpload(array $file, string $uploadDir, int $maxFileSize): array
{
    $originalName = basename($file['name']);

    // Sanitize filename
    $safeName = preg_replace('/[^a-zA-Z0-9._\-äöüÄÖÜß]/', '_', $originalName);
    $safeName = preg_replace('/_{2,}/', '_', $safeName);
    $safeName = trim($safeName, '_');

    if (empty($safeName)) {
        $safeName = 'unnamed_file';
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'name' => $originalName,
            'error' => getUploadErrorMessage($file['error']),
        ];
    }

    // Check file size
    if ($file['size'] > $maxFileSize) {
        $maxMb = round($maxFileSize / 1048576);
        return [
            'success' => false,
            'name' => $originalName,
            'error' => "Datei zu groß (Max: {$maxMb} MB)",
        ];
    }

    // Create date-based subdirectory
    $dateDir = $uploadDir . '/' . date('Y-m-d');
    if (!is_dir($dateDir)) {
        mkdir($dateDir, 0750, true);
    }

    // Prevent overwriting: add timestamp if file exists
    $targetPath = $dateDir . '/' . $safeName;
    if (file_exists($targetPath)) {
        $pathInfo = pathinfo($safeName);
        $nameWithoutExt = $pathInfo['filename'];
        $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $safeName = $nameWithoutExt . '_' . time() . $ext;
        $targetPath = $dateDir . '/' . $safeName;
    }

    // Move file without any compression
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Preserve original permissions
        chmod($targetPath, 0640);

        // Notify via Telegram
        notifyUpload($originalName, $file['size']);

        return [
            'success' => true,
            'name' => $originalName,
            'saved_as' => $safeName,
            'size' => formatFileSize($file['size']),
        ];
    }

    return [
        'success' => false,
        'name' => $originalName,
        'error' => 'Datei konnte nicht gespeichert werden',
    ];
}

function notifyUpload(string $filename, int $size): void
{
    $token = Config::getTelegramBotToken();
    $chatId = Config::getTelegramChatId();

    if (empty($token) || empty($chatId)) {
        return;
    }

    $sizeFormatted = formatFileSize($size);
    $date = date('d.m.Y H:i:s');
    $text = "📁 *Neue Datei hochgeladen*\n\n"
        . "Datei: `{$filename}`\n"
        . "Größe: {$sizeFormatted}\n"
        . "Zeit: {$date} UTC";

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'timeout' => 5,
        ],
    ];

    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

function normalizeFiles(array $files): array
{
    $normalized = [];

    if (is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }
    } else {
        $normalized[] = $files;
    }

    return $normalized;
}

function getUploadErrorMessage(int $error): string
{
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'Datei überschreitet die maximale Upload-Größe des Servers',
        UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet die maximale Formulargröße',
        UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE => 'Keine Datei wurde hochgeladen',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
        UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden',
        UPLOAD_ERR_EXTENSION => 'Upload wurde durch eine Erweiterung blockiert',
    ];

    return $messages[$error] ?? 'Unbekannter Upload-Fehler';
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}
