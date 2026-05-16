<?php

declare(strict_types=1);

class Config
{
    private static ?array $config = null;

    public static function load(): void
    {
        if (self::$config !== null) {
            return;
        }

        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            throw new RuntimeException('Configuration file .env not found. Copy .env.example to .env and fill in your values.');
        }

        self::$config = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                self::$config[$key] = $value;
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (self::$config === null) {
            self::load();
        }
        return self::$config[$key] ?? $default;
    }

    public static function getTelegramBotToken(): string
    {
        return self::get('TELEGRAM_BOT_TOKEN');
    }

    public static function getTelegramChatId(): string
    {
        return self::get('TELEGRAM_CHAT_ID');
    }

    public static function getWebhookSecret(): string
    {
        return self::get('WEBHOOK_SECRET');
    }

    public static function getUploadDir(): string
    {
        $dir = self::get('UPLOAD_DIR', __DIR__ . '/../uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return realpath($dir);
    }

    public static function getMaxFileSize(): int
    {
        return (int) self::get('MAX_FILE_SIZE', '104857600');
    }

    public static function getPasswordFile(): string
    {
        return __DIR__ . '/../data/passwords.json';
    }
}
