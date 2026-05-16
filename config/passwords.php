<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class PasswordManager
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = Config::getPasswordFile();
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([], JSON_PRETTY_PRINT));
            chmod($this->filePath, 0640);
        }
    }

    private function loadPasswords(): array
    {
        $content = file_get_contents($this->filePath);
        if ($content === false) {
            return [];
        }
        $passwords = json_decode($content, true);
        return is_array($passwords) ? $passwords : [];
    }

    private function savePasswords(array $passwords): void
    {
        file_put_contents($this->filePath, json_encode($passwords, JSON_PRETTY_PRINT));
    }

    private function cleanExpired(array &$passwords): void
    {
        $now = time();
        $passwords = array_filter($passwords, function (array $entry) use ($now): bool {
            return $entry['expires_at'] > $now;
        });
        $passwords = array_values($passwords);
        $this->savePasswords($passwords);
    }

    public function addPassword(string $password, string $duration): array
    {
        $seconds = $this->parseDuration($duration);
        if ($seconds <= 0) {
            return ['success' => false, 'message' => 'Ungültige Dauer. Nutze z.B. 1h, 24h, 7d, 30m'];
        }

        $passwords = $this->loadPasswords();
        $this->cleanExpired($passwords);

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $expiresAt = time() + $seconds;

        $passwords[] = [
            'hash' => $hashedPassword,
            'created_at' => time(),
            'expires_at' => $expiresAt,
            'duration' => $duration,
        ];

        $this->savePasswords($passwords);

        $expiryDate = date('d.m.Y H:i:s', $expiresAt);
        return [
            'success' => true,
            'message' => "Passwort gesetzt! Gültig bis: {$expiryDate} (UTC)"
        ];
    }

    public function verifyPassword(string $password): bool
    {
        $passwords = $this->loadPasswords();
        $this->cleanExpired($passwords);

        // Reload after cleanup
        $passwords = $this->loadPasswords();
        $now = time();

        foreach ($passwords as $entry) {
            if ($entry['expires_at'] > $now && password_verify($password, $entry['hash'])) {
                return true;
            }
        }

        return false;
    }

    public function listPasswords(): array
    {
        $passwords = $this->loadPasswords();
        $this->cleanExpired($passwords);
        $passwords = $this->loadPasswords();

        $list = [];
        $now = time();
        foreach ($passwords as $index => $entry) {
            $remaining = $entry['expires_at'] - $now;
            $list[] = [
                'index' => $index + 1,
                'duration' => $entry['duration'],
                'created' => date('d.m.Y H:i', $entry['created_at']),
                'expires' => date('d.m.Y H:i', $entry['expires_at']),
                'remaining' => $this->formatRemaining($remaining),
            ];
        }

        return $list;
    }

    public function deleteAll(): bool
    {
        $this->savePasswords([]);
        return true;
    }

    public function deleteByIndex(int $index): bool
    {
        $passwords = $this->loadPasswords();
        $this->cleanExpired($passwords);
        $passwords = $this->loadPasswords();

        $idx = $index - 1;
        if (!isset($passwords[$idx])) {
            return false;
        }

        array_splice($passwords, $idx, 1);
        $this->savePasswords($passwords);
        return true;
    }

    private function parseDuration(string $duration): int
    {
        $duration = strtolower(trim($duration));
        if (preg_match('/^(\d+)\s*(m|min|minutes?)$/', $duration, $m)) {
            return (int) $m[1] * 60;
        }
        if (preg_match('/^(\d+)\s*(h|hours?|stunden?)$/', $duration, $m)) {
            return (int) $m[1] * 3600;
        }
        if (preg_match('/^(\d+)\s*(d|days?|tage?)$/', $duration, $m)) {
            return (int) $m[1] * 86400;
        }
        if (preg_match('/^(\d+)\s*(w|weeks?|wochen?)$/', $duration, $m)) {
            return (int) $m[1] * 604800;
        }
        return 0;
    }

    private function formatRemaining(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Abgelaufen';
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }

        return implode(' ', $parts) ?: '< 1m';
    }
}
