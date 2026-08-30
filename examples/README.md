# GOWA Laravel Integration Examples

This directory contains executable standalone examples demonstrating how to interact with GOWA in a Laravel environment using `gowa-php/laravel`.

## Available Examples

- **`check-device.php`**: Verifies connectivity with your GOWA server, fetches device details, and syncs status to the `GowaInstance` Eloquent model.
- **`send-text.php`**: Prompts for recipient and message text, and sends a WhatsApp message using the `Gowa` Facade (`Gowa::sendText(...)`).
- **`notification-channel.php`**: Demonstrates sending notifications using Laravel's Notification System with `GowaChannel` and `GowaMessage`.
- **`webhook-listener.php`**: Simulates an incoming GOWA Webhook POST request, verifies the HMAC SHA-256 signature, and dispatches Laravel events (`GowaWebhookReceived`, `GowaMessageReceived`, `GowaMessageAck`).

## Setup & Configuration

1. Install dependencies at the repository root:

```bash
composer install
```

2. Create a local credentials file from the template:

```bash
cp examples/.env.example examples/.env
```

3. Edit `examples/.env` with your GOWA server credentials, device ID, and target test number. (Note: `examples/.env` is ignored by Git — never commit real credentials).

## Running the Examples

### 1. Check Device & Sync Eloquent Model

```bash
php examples/check-device.php
```

### 2. Send a Text Message via Facade

```bash
php examples/send-text.php
```

By default, this script runs interactively and prompts for confirmation (`SEND`). For automated testing, set `GOWA_SEND_MESSAGE=1` in `examples/.env`.

### 3. Laravel Notification Channel Example

```bash
php examples/notification-channel.php
```

### 4. Webhook Handling & Event Dispatcher Example

```bash
php examples/webhook-listener.php
```
