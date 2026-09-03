<div align="center">
  <img src="art/banner.png" alt="gowa-laravel Banner" width="100%" max-width="800">

  # gowa-php/laravel

  **Laravel integration for GOWA — Facade, Notification Channel, Webhook routing, and Eloquent models**

  [![Latest Version](https://img.shields.io/packagist/v/gowa-php/laravel.svg?style=flat-square)](https://packagist.org/packages/gowa-php/laravel)
  [![Total Downloads](https://img.shields.io/packagist/dt/gowa-php/laravel.svg?style=flat-square)](https://packagist.org/packages/gowa-php/laravel)
  [![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
  [![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-777BB4.svg?style=flat-square)](https://php.net)
  [![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20.svg?style=flat-square)](https://laravel.com)

</div>

---

> 🇧🇷 Para ler a documentação em Português, acesse [README.pt.md](README.pt.md).

---

## ⚡ Acknowledgments & Dependencies

This package interacts with the Go backend ecosystem created by the open-source community:

- **[whatsmeow](https://go.mau.fi/whatsmeow)** — The underlying Go library created by [Tulir Asokan](https://github.com/tulir) that reverse-engineers the WhatsApp Web Multi-Device WebSocket protocol and Signal encryption.
- **[go-whatsapp-web-multidevice (GOWA)](https://github.com/aldinokemal/go-whatsapp-web-multidevice)** — The lightweight REST API wrapper created by [Aldino Kemal](https://github.com/aldinokemal) exposing `whatsmeow` over HTTP and Webhooks.
- **[gowa-php/sdk](https://packagist.org/packages/gowa-php/sdk)** — The underlying PHP SDK for GOWA.

---

## Requirements

- PHP >= 8.2
- Laravel 10, 11, or 12
- [`gowa-php/sdk`](https://packagist.org/packages/gowa-php/sdk) ^1.0
- A running instance of the **[GOWA (go-whatsapp-web-multidevice)](https://github.com/aldinokemal/go-whatsapp-web-multidevice)** REST API server (`GOWA_BASE_URL`)

## Installation

```bash
composer require gowa-php/laravel
```

The service provider and `Gowa` facade are registered automatically via Laravel's package discovery.

Publish the config file:

```bash
php artisan vendor:publish --tag=gowa-config
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=gowa-migrations
php artisan migrate
```

## Configuration

```env
GOWA_BASE_URL=https://gowa.yourcompany.com
GOWA_USERNAME=admin
GOWA_PASSWORD=secret
GOWA_TIMEOUT=15
GOWA_DEFAULT_DEVICE_ID=my-default-device-uuid
GOWA_WEBHOOK_SECRET=your_hmac_secret
GOWA_WEBHOOK_PATH=webhooks/gowa
GOWA_WEBHOOK_AUTO_SYNC=true
GOWA_LOG_WEBHOOKS=false
```

> **`GOWA_WEBHOOK_SECRET` is required to receive webhooks.** The GOWA server signs
> every delivery with `X-Hub-Signature-256`, using the device's own secret when it has
> one and its global `WHATSAPP_WEBHOOK_SECRET` otherwise. This package mirrors that
> order: `gowa_instances.webhook_secret` first, then `gowa.webhook.secret`. With no
> secret on either side the signature cannot be verified and the request is rejected
> with `403` -- an unsigned webhook is never accepted.

## Usage

### Fluent Messaging (Recommended)

Send messages with an expressive fluent interface:

```php
use Gowa\Laravel\Facades\Gowa;

// Send plain text
Gowa::to('5511999998888')->text('Hello from Laravel!')->send();

// Specify a sender device (optional; defaults to config or first connected instance)
Gowa::from('device-id')->to('5511999998888')->text('Hello from specific instance!')->send();

// Reply / quote a previous message
Gowa::to($phone)->replyTo($messageId)->text('Replying to your message...')->send();
```

#### Media & Laravel Storage Attachments

Seamlessly attach media from URLs, local paths, streams, or **Laravel Storage Disks (S3, MinIO, Public, Local)**:

```php
// Image (from URL or local file path)
Gowa::to($phone)->image('https://example.com/banner.png', 'Promotional offer!')->send();

// Document directly from Laravel Storage Disk (e.g. Amazon S3) via streaming
Gowa::to($phone)
    ->disk('s3')
    ->document('invoices/2026/inv_1092.pdf', filename: 'Invoice.pdf', caption: 'Your monthly invoice')
    ->send();

// You can also pass the disk inline
Gowa::to($phone)->image('banners/promo.jpg', caption: 'Summer sale', disk: 'public')->send();

// Video & Audio
Gowa::to($phone)->video('videos/demo.mp4', 'Product Demo')->send();
Gowa::to($phone)->audio('podcasts/episode1.mp3')->send();

// Voice note / PTT (Push-To-Talk audio)
Gowa::to($phone)->voice('voice_notes/memo.ogg')->send();

// Sticker (WebP)
Gowa::to($phone)->sticker('stickers/thumbs_up.webp')->send();
```

#### Rich Messages: Locations, Contacts, Polls, Links & Reactions

```php
// Geolocation (Latitude & Longitude)
Gowa::to($phone)->location(-23.55052, -46.633309)->send();

// Contact vCard
Gowa::to($phone)->contact('Jane Doe', '5511988887777')->send();

// Interactive Poll
Gowa::to($phone)
    ->poll('What is the best meeting time?', ['Morning (9am)', 'Afternoon (2pm)', 'Evening (6pm)'], maxSelections: 1)
    ->send();

// Link with rich preview
Gowa::to($phone)->link('https://antigravity.google', 'Antigravity AI Platform')->send();

// Emoji Reaction to a message
Gowa::to($phone)->reaction($messageId, '🔥')->send();
```

#### Direct Message Actions

```php
// Mark message as read / played
Gowa::to($phone)->markRead($messageId, withTyping: false);
Gowa::to($phone)->markPlayed($audioMessageId);

// Revoke (delete for everyone) or Star
Gowa::to($phone)->revoke($messageId);
Gowa::to($phone)->star($messageId);
```

### Notification Channel

Implement `toGowa()` on your notification and `routeNotificationForGowa()` on your notifiable. `GowaMessage` supports all fluent media and storage methods:

```php
use Gowa\Laravel\Notifications\GowaChannel;
use Gowa\Laravel\Notifications\GowaMessage;
use Illuminate\Notifications\Notification;

class OrderInvoiceNotification extends Notification
{
    public function __construct(public Order $order) {}

    public function via(mixed $notifiable): array
    {
        return [GowaChannel::class];
    }

    public function toGowa(mixed $notifiable): GowaMessage
    {
        return GowaMessage::create()
            ->disk('s3')
            ->document("invoices/{$this->order->id}.pdf", filename: 'Invoice.pdf', caption: "Here is your invoice for order #{$this->order->id}!");
    }
}

// On your User model:
public function routeNotificationForGowa(): string
{
    return $this->phone_number; // e.g. '5511999998888'
}
```

### Webhook Events & Automatic Database Sync

The package registers a POST route at `{GOWA_WEBHOOK_PATH}/{deviceId}` automatically. It verifies the HMAC signature using the `webhook_secret` stored on the `GowaInstance` model, then dispatches typed Laravel events.

#### Automatic Database Sync (`GOWA_WEBHOOK_AUTO_SYNC=true`)

When enabled (default), the package automatically:
- Creates / updates `GowaConversation` with the sender details.
- Inserts incoming messages into `GowaMessage` (with direction `inbound` and status `delivered`).
- Updates `GowaMessage` delivery and read receipts (`delivered_at`, `read_at`, status `read`) upon receiving ack webhooks.
- Records outbound messages when using `Gowa::to()->send()`.
- Listeners implement `ShouldQueue` — processing executes asynchronously on your configured Laravel queue worker or synchronously (`sync`).
- Every accepted delivery is written to `gowa_webhook_calls` by the controller *before* the events are dispatched, with the request URL and headers (minus `authorization`, `cookie` and `proxy-authorization`). If one of the package's sync listeners throws, that row is flipped to `processed = false` and the exception is stored, so a failure is visible in the table and not only in `failed_jobs`.

#### Custom Event Listeners

You can also listen to typed events in your application:

```php
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Laravel\Webhook\Events\GowaMessageAck;
use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;

// Any incoming webhook (before type-specific events)
Event::listen(GowaWebhookReceived::class, function (GowaWebhookReceived $event) {
    // $event->deviceId is always the device the delivery was addressed to.
    // $event->instanceId is null when that device has no row in gowa_instances.
    Log::info('GOWA webhook', [
        'event'    => $event->event->value,
        'device'   => $event->deviceId,
        'instance' => $event->instanceId,
    ]);
});

// Incoming message
Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) {
    $message = $event->message; // Gowa\Sdk\Webhook\Dto\IncomingMessage
    // process custom logic or trigger AI agent...
});

// Message read/delivered acknowledgement
Event::listen(GowaMessageAck::class, function (GowaMessageAck $event) {
    $ack = $event->ack; // Gowa\Sdk\Webhook\Dto\IncomingAck
});

// Tip: If your listener catches an exception and wants to flag the webhook audit row as failed:
use Gowa\Laravel\Models\GowaWebhookCall;

try {
    // custom processing...
} catch (\Throwable $e) {
    GowaWebhookCall::markFailed($event->webhookCallId, $e);
    throw $e;
}
```

### Eloquent Models

```php
use Gowa\Laravel\Models\GowaInstance;

// Find instance and verify it's connected
$instance = GowaInstance::where('device_id', 'my-device')->firstOrFail();
$instance->status->isConnected(); // bool

// Build a GowaClient scoped to this instance
$client = $instance->client();
$client->sendText('5511999998888', 'Hello!');

// Access conversations and messages
$instance->conversations()->with('messages')->get();
```

### Swapping Models

Point the config to your own model classes (useful when adding custom columns or relations):

```php
// config/gowa.php
'models' => [
    'instance'     => App\Models\WhatsappInstance::class,
    'conversation' => App\Models\WhatsappConversation::class,
    'message'      => App\Models\WhatsappMessage::class,
    'webhook_call' => App\Models\WhatsappWebhookCall::class,
],
```

### Teams Support

Enable multi-tenant scoping by adding a `team_id` column to migrations:

```env
GOWA_TEAMS=true
GOWA_TEAM_FOREIGN_KEY=team_id
```

Publish and re-run migrations after enabling this setting.

## Upgrading to v1.1.0

### Breaking Changes & Migration Steps

1. **`GOWA_WEBHOOK_SECRET` is now mandatory**: All webhook deliveries must be signed via HMAC-SHA256. If a device has no device-specific `webhook_secret`, it falls back to the global `gowa.webhook.secret`. Unsigned webhook requests will receive `403 Forbidden` (or `404` if the device is not registered in the database and no global secret is set).
2. **Publish the Audit Table Migration**: A new migration (`000004_create_gowa_webhook_calls_table.php`) records all webhook deliveries and failure states. Run:
   ```bash
   php artisan vendor:publish --tag=gowa-migrations
   php artisan migrate
   ```
3. **Webhook Event Signatures**: The event constructors (`GowaWebhookReceived`, `GowaMessageReceived`, `GowaMessageAck`, `GowaMessageReaction`) now accept `?int $instanceId` and `string $deviceId`. If your application instantiates these events manually in tests, update calls to provide the `$deviceId`.
4. **Configuration Update**: Re-publish configuration if updating from v1.0:
   ```bash
   php artisan vendor:publish --tag=gowa-config --force
   ```

## Running Tests

By default, tests run using SQLite in-memory without requiring any external services:

```bash
composer test
# or explicitly:
composer test:sqlite
```

To run tests against MySQL and PostgreSQL using Docker:

```bash
# Start MySQL and PostgreSQL containers
docker compose up -d

# Run test suites against specific database drivers
composer test:mysql
composer test:pgsql
```

## ⚠️ Disclaimer & Terms of Use

This software is an open-source library created for **educational, research, and testing laboratory purposes**.

- **Third-Party Terms of Service**: Users of this library are solely responsible for complying with WhatsApp's Terms of Service, Meta's Platform Policies, and the terms of any third-party services utilized.
- **Automated Messaging & Policy Compliance**: Automated or unauthorized messaging may violate platform terms. Users must ensure strict compliance with applicable privacy laws (e.g., GDPR, LGPD), user consent requirements, and platform guidelines.
- **No Warranty & Liability**: This software is provided "as is", without warranty of any kind, express or implied. The authors and contributors assume no liability for any account bans, data loss, service interruptions, or misuse of this library.

## License

MIT — see [LICENSE](LICENSE).
