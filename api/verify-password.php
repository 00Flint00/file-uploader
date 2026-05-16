<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/passwords.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate limiting via session
session_start();
$now = time();
$attempts = $_SESSION['password_attempts'] ?? [];

// Clean old attempts (older than 15 minutes)
$attempts = array_filter($attempts, function (int $timestamp) use ($now): bool {
    return ($now - $timestamp) < 900;
});

// Max 10 attempts per 15 minutes
if (count($attempts) >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Zu viele Versuche. Bitte warte 15 Minuten.']);
    exit;
}

// Read input
$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Passwort erforderlich']);
    exit;
}

// Record attempt
$attempts[] = $now;
$_SESSION['password_attempts'] = $attempts;

$passwordManager = new PasswordManager();

if ($passwordManager->verifyPassword($password)) {
    // Generate upload token (valid for 30 minutes)
    $token = bin2hex(random_bytes(32));
    $_SESSION['upload_token'] = $token;
    $_SESSION['upload_token_expires'] = $now + 1800;
    $_SESSION['password_attempts'] = []; // Reset attempts on success

    echo json_encode([
        'success' => true,
        'token' => $token,
        'message' => 'Zugang gewährt'
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Falsches Passwort oder abgelaufen'
    ]);
}
