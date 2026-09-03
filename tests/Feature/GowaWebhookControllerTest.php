<?php

declare(strict_types=1);

use Gowa\Laravel\Models\GowaInstance;
use Gowa\Laravel\Webhook\Events\GowaMessageAck;
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Laravel\Webhook\Events\GowaMessageReaction;
use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function makeInstance(array $attrs = []): GowaInstance
{
    return GowaInstance::create(array_merge([
        'name'           => 'Test Instance',
        'device_id'      => 'test-device-01',
        'status'         => 'open',
        'webhook_secret' => null,
    ], $attrs));
}

function signedRequest(string $payload, string $secret): string
{
    return 'sha256=' . hash_hmac('sha256', $payload, $secret);
}

// ── 404 / 403 ────────────────────────────────────────────────────────────────

test('returns 404 when device_id not found', function () {
    $this->postJson(route('gowa.webhook', ['deviceId' => 'unknown-device']))
        ->assertStatus(404);
});

test('returns 403 when signature invalid', function () {
    makeInstance(['webhook_secret' => 'correct-secret']);

    $this->postJson(
        route('gowa.webhook', ['deviceId' => 'test-device-01']),
        ['event' => 'message'],
        ['X-Gowa-Signature' => 'sha256=invalidsig'],
    )->assertStatus(403);
});

// ── No device secret → falls back to the global secret ───────────────────────

test('rejects unsigned request when instance has no secret', function () {
    Event::fake();
    withGlobalSecret();
    makeInstance(['webhook_secret' => null]);

    $this->postJson(
        route('gowa.webhook', ['deviceId' => 'test-device-01']),
        ['event' => 'unknown'],
    )->assertStatus(403);

    Event::assertNotDispatched(GowaWebhookReceived::class);
});

test('accepts request signed with the global secret when instance has no secret', function () {
    Event::fake();
    makeInstance(['webhook_secret' => null]);

    postWebhook('test-device-01', ['event' => 'unknown'])->assertStatus(200);

    Event::assertDispatched(GowaWebhookReceived::class);
});

test('rejects request when neither the instance nor the config define a secret', function () {
    Event::fake();
    config(['gowa.webhook.secret' => null]);
    makeInstance(['webhook_secret' => null]);

    postWebhook('test-device-01', ['event' => 'unknown'], 'any-secret')->assertStatus(403);

    Event::assertNotDispatched(GowaWebhookReceived::class);
});

// ── GowaWebhookReceived fires for all events ──────────────────────────────────

test('dispatches GowaWebhookReceived for every valid webhook', function () {
    Event::fake();
    makeInstance();

    postWebhook('test-device-01', ['event' => 'unknown.type'])->assertStatus(200);

    Event::assertDispatched(GowaWebhookReceived::class, function ($e) {
        return $e->instanceId > 0;
    });
});

// ── Type-specific events ──────────────────────────────────────────────────────

test('dispatches GowaMessageReceived for message event', function () {
    Event::fake();
    makeInstance();

    postWebhook('test-device-01', [
        'event'   => 'message',
        'payload' => [
            'id'      => 'MSG-001',
            'chat_id' => '5511999999999@s.whatsapp.net',
            'body'    => 'Hello!',
        ],
    ])->assertStatus(200);

    Event::assertDispatched(GowaMessageReceived::class, function ($e) {
        return $e->message->id === 'MSG-001' && $e->message->body === 'Hello!';
    });
});

test('dispatches GowaMessageAck for message.ack event', function () {
    Event::fake();
    makeInstance();

    postWebhook('test-device-01', [
        'event'   => 'message.ack',
        'payload' => [
            'ids'          => ['MSG-001', 'MSG-002'],
            'receipt_type' => 'read',
            'chat_id'      => '5511999999999@s.whatsapp.net',
        ],
    ])->assertStatus(200);

    Event::assertDispatched(GowaMessageAck::class, function ($e) {
        return $e->ack->isRead() && count($e->ack->messageIds) === 2;
    });
});

test('dispatches GowaMessageReaction for message.reaction event', function () {
    Event::fake();
    makeInstance();

    postWebhook('test-device-01', [
        'event'   => 'message.reaction',
        'payload' => [
            'id'                 => 'REACT-001',
            'chat_id'            => '5511999999999@s.whatsapp.net',
            'reacted_message_id' => 'MSG-001',
            'reaction'           => '👍',
        ],
    ])->assertStatus(200);

    Event::assertDispatched(GowaMessageReaction::class, function ($e) {
        return $e->reaction->emoji === '👍' && $e->reaction->targetMessageId === 'MSG-001';
    });
});

// ── Signed requests ───────────────────────────────────────────────────────────

test('accepts valid HMAC-signed request', function () {
    Event::fake();
    $secret = 'super-secret-key';
    makeInstance(['webhook_secret' => $secret]);

    $body = json_encode(['event' => 'unknown']);
    $sig  = signedRequest($body, $secret);

    $this->call(
        'POST',
        route('gowa.webhook', ['deviceId' => 'test-device-01']),
        [],
        [],
        [],
        ['HTTP_X_GOWA_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertStatus(200);

    Event::assertDispatched(GowaWebhookReceived::class);
});

test('accepts webhook verified against global secret when instance is not in database', function () {
    Event::fake();
    $secret = 'global-hmac-secret';
    config(['gowa.webhook.secret' => $secret]);

    $body = json_encode(['event' => 'message', 'payload' => ['id' => 'GLOB-1', 'chat_id' => '5511999999999@s.whatsapp.net', 'body' => 'Hi']]);
    $sig  = signedRequest($body, $secret);

    $this->call(
        'POST',
        route('gowa.webhook', ['deviceId' => 'stateless-device']),
        [],
        [],
        [],
        ['HTTP_X_GOWA_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertStatus(200);

    Event::assertDispatched(GowaMessageReceived::class);
});


test('rejects unsigned webhook for the default device when no global secret is set', function () {
    Event::fake();
    config(['gowa.webhook.secret' => null]);
    config(['gowa.default_device_id' => 'default-device']);

    $body = json_encode(['event' => 'message', 'payload' => ['id' => 'FAKE-1', 'chat_id' => '5511999999999@s.whatsapp.net', 'body' => 'spoofed']]);

    $this->call(
        'POST',
        route('gowa.webhook', ['deviceId' => 'default-device']),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertStatus(404);

    Event::assertNotDispatched(GowaWebhookReceived::class);
    Event::assertNotDispatched(GowaMessageReceived::class);
});

test('rejects webhook signed with the wrong secret for the default device', function () {
    Event::fake();
    config(['gowa.webhook.secret' => 'global-hmac-secret']);
    config(['gowa.default_device_id' => 'default-device']);

    $body = json_encode(['event' => 'message', 'payload' => ['id' => 'FAKE-2', 'chat_id' => '5511999999999@s.whatsapp.net', 'body' => 'spoofed']]);
    $sig  = signedRequest($body, 'attacker-secret');

    $this->call(
        'POST',
        route('gowa.webhook', ['deviceId' => 'default-device']),
        [],
        [],
        [],
        ['HTTP_X_GOWA_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertStatus(403);

    Event::assertNotDispatched(GowaWebhookReceived::class);
});
