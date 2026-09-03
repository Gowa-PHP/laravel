<?php

declare(strict_types=1);

$app = require __DIR__ . '/bootstrap.php';

use Gowa\Laravel\Models\GowaInstance;
use Gowa\Laravel\Webhook\Events\GowaMessageAck;
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

$deviceId = 'my-instance-uuid';
$secret = 'test-webhook-secret';

// 1. Create instance record in DB
$instance = GowaInstance::create([
    'name'           => 'Webhook Demo Instance',
    'device_id'      => $deviceId,
    'status'         => 'open',
    'webhook_secret' => $secret,
]);

echo "1. Registered GowaInstance in DB with ID {$instance->id} and secret '{$secret}'.\n\n";

// 2. Register Event Listeners
echo "2. Registering Laravel Event Listeners...\n";

Event::listen(GowaWebhookReceived::class, function (GowaWebhookReceived $event) {
    echo "  [Event] GowaWebhookReceived fired! Raw event: {$event->event->value}, Instance ID: {$event->instanceId}\n";
});

Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) {
    echo "  [Event] GowaMessageReceived fired! Message ID: {$event->message->id}, Body: \"{$event->message->body}\"\n";
});

Event::listen(GowaMessageAck::class, function (GowaMessageAck $event) {
    echo "  [Event] GowaMessageAck fired! Receipt: {$event->ack->receiptType}, Message IDs: " . implode(', ', $event->ack->messageIds) . "\n";
});

// 3. Simulate incoming webhook payload & HMAC signature
$payload = json_encode([
    'event'   => 'message',
    'payload' => [
        'id'      => 'WAMID.001',
        'chat_id' => '5511999998888@s.whatsapp.net',
        'body'    => 'Hello from WhatsApp Webhook!',
    ],
]);

$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

echo "\n3. Dispatching simulated Webhook POST request to route /webhooks/gowa/{$deviceId}...\n";

$request = Request::create(
    "/webhooks/gowa/{$deviceId}",
    'POST',
    content: $payload,
);
$request->headers->set('X-Gowa-Signature', $signature);
$request->headers->set('Content-Type', 'application/json');

$response = $app->handle($request);

echo "\n✔ HTTP Response Status: " . $response->getStatusCode() . "\n";
echo "✔ Webhook processing completed successfully!\n";
