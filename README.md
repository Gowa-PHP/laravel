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
GOWA_WEBHOOK_SECRET=your_hmac_secret
GOWA_WEBHOOK_PATH=webhooks/gowa
```

## Usage

### Facade

```php
use Gowa\Laravel\Facades\Gowa;

// Send a text message
Gowa::sendText('5511999998888', 'Hello from Laravel!');

// Send media
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;

$media = new MediaPayload(
    type: MediaType::Document,
    upload: MediaUpload::fromPath('/path/to/invoice.pdf'),
);
Gowa::sendMedia('5511999998888', $media, 'Your invoice');
```

### Notification Channel

Implement `toGowa()` on your notification and `routeNotificationForGowa()` on your notifiable:

```php
use Gowa\Laravel\Notifications\GowaMessage;

class OrderShipped extends Notification
{
    public function via(mixed $notifiable): array
    {
        return [\Gowa\Laravel\Notifications\GowaChannel::class];
    }

    public function toGowa(mixed $notifiable): GowaMessage
    {
        return GowaMessage::create("Your order #{$this->order->id} has shipped!");
    }
}

// On your User model:
public function routeNotificationForGowa(): string
{
    return $this->phone_number; // e.g. '5511999998888'
}
```

### Webhook Events

The package registers a POST route at `{GOWA_WEBHOOK_PATH}/{deviceId}` automatically. It verifies the HMAC signature using the `webhook_secret` stored on the `GowaInstance` model, then dispatches typed Laravel events.

Listen to them in `EventServiceProvider` or using `#[AsListener]`:

```php
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Laravel\Webhook\Events\GowaMessageAck;
use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;

// Any incoming webhook (before type-specific events)
Event::listen(GowaWebhookReceived::class, function (GowaWebhookReceived $event) {
    Log::info('GOWA webhook', ['event' => $event->event->value, 'instance' => $event->instanceId]);
});

// Incoming message
Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) {
    $message = $event->message; // Gowa\Sdk\Webhook\Dto\IncomingMessage
    // handle...
});

// Message read/delivered acknowledgement
Event::listen(GowaMessageAck::class, function (GowaMessageAck $event) {
    $ack = $event->ack; // Gowa\Sdk\Webhook\Dto\IncomingAck
    // update message status...
});
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
],
```

### Teams Support

Enable multi-tenant scoping by adding a `team_id` column to migrations:

```env
GOWA_TEAMS=true
GOWA_TEAM_FOREIGN_KEY=team_id
```

Publish and re-run migrations after enabling this setting.

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
