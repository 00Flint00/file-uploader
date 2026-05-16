# Datei-Uploader

Passwortgeschützter Datei-Uploader mit Telegram-Bot-Steuerung für Shared Hosting (PHP/Apache).

## Features

- **Passwortgeschützter Upload** — Passwort wird per Telegram Bot gesetzt, inklusive Gültigkeitsdauer
- **Dunkles Design** — Modernes Dark Theme mit Akzentfarben
- **Alle Dateitypen** — Text, Bilder, Ordner, ZIP, 7z, etc.
- **Keine Komprimierung** — Dateien bleiben unverändert
- **Sicherheit** — Telegram-Zugangsdaten und Dateipfade sind durch `.htaccess` geschützt
- **Rate Limiting** — Max. 10 Passwort-Versuche pro 15 Minuten
- **Upload-Benachrichtigung** — Bei jedem Upload erhältst du eine Telegram-Nachricht

## Voraussetzungen

- Webhosting mit PHP 7.4+ und Apache (z.B. Namecheap Shared Hosting)
- Telegram Bot (erstellt über [@BotFather](https://t.me/BotFather))

## Installation

### 1. Telegram Bot erstellen

1. Öffne [@BotFather](https://t.me/BotFather) in Telegram
2. Sende `/newbot` und folge den Anweisungen
3. Notiere dir den **Bot Token**
4. Starte den Bot und sende eine Nachricht
5. Deine **Chat ID** findest du unter: `https://api.telegram.org/bot<DEIN_TOKEN>/getUpdates`

### 2. Dateien hochladen

1. Lade alle Dateien auf dein Webhosting hoch (z.B. via cPanel File Manager oder FTP)
2. Kopiere `.env.example` nach `.env`:
   ```
   cp .env.example .env
   ```
3. Bearbeite `.env` mit deinen Daten:
   ```
   TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
   TELEGRAM_CHAT_ID=123456789
   WEBHOOK_SECRET=ein_zufaelliger_string_hier
   UPLOAD_DIR=/home/dein_cpanel_user/secure_uploads
   ```

### 3. Webhook registrieren

Rufe einmal im Browser auf:
```
https://deinedomain.de/api/setup-webhook.php?run=1
```

Oder per CLI:
```bash
php api/setup-webhook.php https://deinedomain.de
```

**Danach `setup-webhook.php` löschen!**

### 4. Upload-Verzeichnis erstellen

Erstelle das Upload-Verzeichnis **außerhalb** des `public_html`-Ordners:
```bash
mkdir -p /home/dein_cpanel_user/secure_uploads
chmod 750 /home/dein_cpanel_user/secure_uploads
```

## Telegram Bot Befehle

| Befehl | Beschreibung |
|--------|-------------|
| `/setpassword <passwort> <dauer>` | Neues Passwort setzen (z.B. `/setpassword meinPW 24h`) |
| `/listpasswords` | Alle aktiven Passwörter anzeigen |
| `/deletepassword <nr>` | Passwort nach Nummer löschen |
| `/deleteall` | Alle Passwörter löschen |
| `/help` | Hilfe anzeigen |

### Gültigkeitsdauer

- `30m` — 30 Minuten
- `1h` — 1 Stunde
- `24h` — 24 Stunden
- `7d` — 7 Tage
- `2w` — 2 Wochen

## Sicherheit

- `.env`, Passwort-Daten und Konfigurationsdateien sind per `.htaccess` geschützt
- Uploads werden in einem nicht-öffentlichen Verzeichnis gespeichert
- PHP-Ausführung ist im Upload-Verzeichnis deaktiviert
- Passwörter werden mit bcrypt gehasht
- Rate Limiting gegen Brute-Force
- CSRF-Schutz durch Session-Token
- Sicherheitsheader (X-Content-Type-Options, X-Frame-Options, CSP, etc.)

## Projektstruktur

```
├── .htaccess              # Hauptregeln: Schutz sensibler Dateien
├── .env.example           # Beispiel-Konfiguration
├── index.html             # Frontend (Dark Theme Upload-UI)
├── css/style.css          # Styles
├── js/app.js              # Frontend-Logik
├── api/
│   ├── .htaccess          # API-Verzeichnis-Schutz
│   ├── upload.php         # Upload-Endpunkt
│   ├── verify-password.php # Passwort-Verifizierung
│   ├── telegram-webhook.php # Telegram Bot Webhook
│   └── setup-webhook.php  # Einmalige Webhook-Registrierung
├── config/
│   ├── .htaccess          # Zugriff komplett blockiert
│   ├── config.php         # Konfigurationsklasse
│   └── passwords.php      # Passwort-Verwaltung
├── data/
│   └── .htaccess          # Zugriff komplett blockiert
└── uploads/
    └── .htaccess          # Direktzugriff blockiert, PHP deaktiviert
```
