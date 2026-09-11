<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Gowa\Laravel\Facades\Gowa;
use Gowa\Laravel\GowaServiceProvider;
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\GowaClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\Foundation\Application;

echo "=======================================================\n";
echo "  GOWA Laravel - Stateless Mode (Driver-Only) Demo\n";
echo "=======================================================\n\n";

// 1. Bootstrap Minimal Laravel Application (WITHOUT database or migrations)
$basePath = __DIR__ . '/../vendor/orchestra/testbench-core/laravel';
$app = (new Application($basePath))->createApplication();
$app->register(GowaServiceProvider::class);

$deviceId = 'my-stateless-device';
$webhookSecret = 'my-global-hmac-secret';

// 2. Configure package in Stateless mode
config([
    'gowa.stateless'         => true,
    'gowa.default_device_id' => $deviceId,
    'gowa.webhook.secret'    => $webhookSecret,
    'gowa.webhook.path'      => 'webhooks/gowa',
]);

$app->boot();

echo "✔ Application booted with GOWA_STATELESS=true\n";
echo "✔ Package migrations and Eloquent models are completely bypassed.\n\n";

// 3. Demonstrate Outbound Message via Gowa Facade (Mocking the HTTP client)
echo "--- Step 1: Outbound Messaging (Fluent API) ---\n";

$mockClient = Mockery::mock(GowaClient::class);
$mockClient->shouldReceive('sendText')
    ->once()
    ->with($deviceId, '5511999998888', 'Hello from Stateless mode!', null)
    ->andReturn(new SentMessage('WAMID.DEMO.001'));

$app->instance(GowaClient::class, $mockClient);

$sent = Gowa::to('5511999998888')
    ->text('Hello from Stateless mode!')
    ->send();

echo "✔ Sent Message ID: {$sent->providerMessageId}\n";
echo "✔ No database tables queried or updated.\n\n";

// 4. Demonstrate Webhook Ingestion with Custom Application Listener & Media
echo "--- Step 2: Inbound Webhook with Media & Custom Application Listener ---\n";

// Emulate a custom database or store in the host application
$customApplicationMessages = [];

Event::listen(GowaWebhookReceived::class, function (GowaWebhookReceived $event) {
    echo "  [Event] GowaWebhookReceived fired for device: {$event->deviceId} (Instance ID is null: " . var_export($event->instanceId === null, true) . ")\n";
});

Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) use (&$customApplicationMessages) {
    echo "  [Event] GowaMessageReceived fired! ID: {$event->message->id}, Type: {$event->message->type}\n";

    // Application persists directly in its own data structure / model
    $customApplicationMessages[] = [
        'device'      => $event->deviceId,
        'message_id'  => $event->message->id,
        'phone'       => $event->message->phone,
        'sender_name' => $event->message->senderName,
        'type'        => $event->message->type,
        'text'        => $event->message->body,
        'media_url'   => $event->mediaUrl(),
        'media_mime'  => $event->mediaMime(),
        'media_file'  => $event->mediaFilename(),
        'is_voice'    => $event->isVoiceNote(),
        'is_live_loc' => $event->isLiveLocation(),
        'latitude'    => $event->locationCoordinates()?->latitude,
        'longitude'   => $event->locationCoordinates()?->longitude,
        'received_at' => now()->toIso8601String(),
    ];
});

// Simulate incoming document webhook POST request with HMAC-SHA256 signature
$payload = json_encode([
    'event'   => 'message',
    'payload' => [
        'id'                  => 'WAMID.DOC.999',
        'chat_id'             => '5511988887777@s.whatsapp.net',
        'sender_display_name' => 'Carlos Souza',
        'document'            => [
            'url'      => 'https://gowa.example.com/media/download/contract_1042.pdf',
            'mimetype' => 'application/pdf',
            'filename' => 'Contrato_1042.pdf',
            'caption'  => 'Segue o contrato em anexo.',
        ],
        'timestamp' => '2026-09-11T12:00:00Z',
    ],
]);

$signature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

$request = Request::create(
    "/webhooks/gowa/{$deviceId}",
    'POST',
    content: $payload,
);
$request->headers->set('X-Hub-Signature-256', $signature);
$request->headers->set('Content-Type', 'application/json');

$response = $app->handle($request);

echo "\n✔ Webhook HTTP Status: {$response->getStatusCode()}\n";
echo "✔ Persisted in Host Application Store:\n";
print_r($customApplicationMessages);

echo "\n=======================================================\n";
echo "  Stateless demo finished successfully!\n";
echo "=======================================================\n";
