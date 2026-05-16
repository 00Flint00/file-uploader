---
name: testing-file-uploader
description: Test the Datei-Uploader app locally. Use when verifying password authentication, file upload, or UI changes.
---

# Testing the Datei-Uploader

## Prerequisites
- PHP CLI installed (`sudo apt-get install -y php-cli`)
- Playwright for Python (`pip install playwright`)

## Setup

1. Copy `.env.example` to `.env` and configure for local testing:
   ```bash
   cp .env.example .env
   # Set UPLOAD_DIR to the local uploads/ directory
   # Set fake TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID (Telegram notifications will fail silently)
   # Set any WEBHOOK_SECRET
   ```

2. Create a test password via PHP CLI:
   ```bash
   php -r "require_once 'config/config.php'; require_once 'config/passwords.php'; \$pm = new PasswordManager(); \$result = \$pm->addPassword('testpass123', '24h'); echo json_encode(\$result);"
   ```

3. Start the PHP built-in server:
   ```bash
   php -S localhost:8080 &
   ```

4. Open the app in Chrome:
   ```bash
   google-chrome http://localhost:8080
   ```

## Testing via Browser

- **Password popup**: Verify dark theme, centered modal, lock icon
- **Wrong password**: Enter wrong password, expect red error message "Falsches Passwort oder abgelaufen"
- **Correct password**: Enter `testpass123`, modal should disappear, upload area should appear
- **Toggle password visibility**: Click eye icon next to password input

## Testing File Upload via Playwright CDP

Since the native file picker is hard to interact with via GUI automation, use Playwright CDP:

```python
from playwright.async_api import async_playwright
import asyncio

async def test():
    async with async_playwright() as p:
        browser = await p.chromium.connect_over_cdp("http://localhost:29229")
        page = browser.contexts[0].pages[0]  # find the correct tab
        file_input = page.locator("#fileInput")
        await file_input.set_input_files("/path/to/test-file.txt")
        await page.click("#uploadBtn")
        # Check status message
        status = await page.locator("#statusMessage").text_content()
        print(status)  # Should contain "erfolgreich hochgeladen"

asyncio.run(test())
```

## Verifying Upload

After uploading, check the file on disk:
```bash
find uploads/ -type f -not -name ".htaccess" -not -name ".gitkeep"
cat uploads/<date>/filename.txt  # Verify content is unmodified
```

## Limitations of Local Testing

- `.htaccess` rules are NOT enforced by PHP's built-in server (only Apache)
- Telegram bot webhook cannot be tested without a real bot token
- Rate limiting is session-based; clearing cookies resets it
- Folder drag-and-drop requires native browser drag interaction

## Devin Secrets Needed

No secrets needed for local testing. For production testing:
- `TELEGRAM_BOT_TOKEN` — from @BotFather
- `TELEGRAM_CHAT_ID` — your Telegram user/chat ID
