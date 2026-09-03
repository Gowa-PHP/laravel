<?php

declare(strict_types=1);

use Gowa\Laravel\Enums\GowaInstanceStatus;
use Gowa\Laravel\Enums\GowaMessageDirection;
use Gowa\Laravel\Enums\GowaMessageStatus;
use Gowa\Laravel\Facades\Gowa;
use Gowa\Laravel\Models\GowaConversation;
use Gowa\Laravel\Models\GowaInstance;
use Gowa\Laravel\Models\GowaMessage;
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\GowaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('incoming webhook message auto-creates conversation and message in database', function () {
    $instance = GowaInstance::create([
        'name'           => 'Sales Instance',
        'device_id'      => 'dev-sync-1',
        'status'         => GowaInstanceStatus::Open,
        'webhook_secret' => null,
    ]);

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'                  => 'WAMID.SYNC.001',
            'chat_id'             => '5511999991111@s.whatsapp.net',
            'sender_display_name' => 'Alice',
            'body'                => 'Hello from WhatsApp!',
            'timestamp'           => '2026-09-01T10:00:00Z',
        ],
    ];

    $response = postWebhook('dev-sync-1', $payload);
    $response->assertOk();

    // Verify conversation
    $conversation = GowaConversation::where('instance_id', $instance->id)
        ->where('contact_jid', '5511999991111@s.whatsapp.net')
        ->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->contact_name)->toBe('Alice')
        ->and($conversation->contact_phone)->toBe('5511999991111');

    // Verify message
    $message = GowaMessage::where('instance_id', $instance->id)
        ->where('message_id', 'WAMID.SYNC.001')
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->conversation_id)->toBe($conversation->id)
        ->and($message->direction)->toBe(GowaMessageDirection::Inbound)
        ->and($message->status)->toBe(GowaMessageStatus::Delivered)
        ->and($message->body)->toBe('Hello from WhatsApp!');

    // Verify instance last_seen_at updated
    $instance->refresh();
    expect($instance->last_seen_at)->not->toBeNull();
});

test('incoming webhook message.ack updates message status to read or delivered', function () {
    $instance = GowaInstance::create([
        'name'      => 'Sales Instance',
        'device_id' => 'dev-sync-2',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $message = GowaMessage::create([
        'instance_id' => $instance->id,
        'message_id'  => 'OUT.MSG.001',
        'direction'   => GowaMessageDirection::Outbound,
        'status'      => GowaMessageStatus::Sent,
        'type'        => 'text',
        'body'        => 'Outbound message',
        'sent_at'     => now(),
    ]);

    // Send Read Ack
    $payload = [
        'event'   => 'message.ack',
        'payload' => [
            'ids'          => ['OUT.MSG.001'],
            'receipt_type' => 'read',
            'chat_id'      => '5511999992222@s.whatsapp.net',
        ],
    ];

    $response = postWebhook('dev-sync-2', $payload);
    $response->assertOk();

    $message->refresh();
    expect($message->status)->toBe(GowaMessageStatus::Read)
        ->and($message->read_at)->not->toBeNull();
});

test('outbound message via Gowa::to auto-creates conversation and message in database', function () {
    $instance = GowaInstance::create([
        'name'      => 'Sales Instance',
        'device_id' => 'dev-sync-3',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('dev-sync-3', '5511999993333', 'Test outbound sync', null)
        ->andReturn(new SentMessage('OUT.SENT.999'));

    $res = Gowa::from('dev-sync-3')
        ->to('5511999993333')
        ->text('Test outbound sync')
        ->send($client);

    expect($res->providerMessageId)->toBe('OUT.SENT.999');

    // Verify conversation
    $conversation = GowaConversation::where('instance_id', $instance->id)
        ->where('contact_jid', '5511999993333@s.whatsapp.net')
        ->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->contact_phone)->toBe('5511999993333');

    // Verify outbound message
    $message = GowaMessage::where('instance_id', $instance->id)
        ->where('message_id', 'OUT.SENT.999')
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->conversation_id)->toBe($conversation->id)
        ->and($message->direction)->toBe(GowaMessageDirection::Outbound)
        ->and($message->status)->toBe(GowaMessageStatus::Sent)
        ->and($message->body)->toBe('Test outbound sync');
});

test('auto_sync can be disabled via config', function () {
    config(['gowa.webhook.auto_sync' => false]);

    $instance = GowaInstance::create([
        'name'      => 'Disabled Sync',
        'device_id' => 'dev-disabled',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.NO.SYNC',
            'chat_id' => '5511999994444@s.whatsapp.net',
            'body'    => 'Should not be stored in DB',
        ],
    ];

    $response = postWebhook('dev-disabled', $payload);
    $response->assertOk();

    expect(GowaConversation::where('instance_id', $instance->id)->count())->toBe(0)
        ->and(GowaMessage::where('instance_id', $instance->id)->count())->toBe(0);
});

test('webhook logging logs request when enabled', function () {
    config([
        'gowa.webhook.log_requests' => true,
    ]);

    Log::spy();

    $instance = GowaInstance::create([
        'name'      => 'Log Instance',
        'device_id' => 'dev-log',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.LOG',
            'chat_id' => '5511999995555@s.whatsapp.net',
            'body'    => 'Log message',
        ],
    ];

    $response = postWebhook('dev-log', $payload);
    $response->assertOk();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(function ($msg, $context) {
            return str_contains($msg, 'GOWA Webhook Received')
                && ($context['event'] ?? '') === 'message';
        });
});

test('incoming webhook records audit call in gowa_webhook_calls table', function () {
    $instance = GowaInstance::create([
        'name'      => 'Audit Instance',
        'device_id' => 'dev-audit',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.AUDIT.123',
            'chat_id' => '5511999996666@s.whatsapp.net',
            'body'    => 'Audit message body',
        ],
    ];

    $response = postWebhook('dev-audit', $payload);
    $response->assertOk();

    $call = \Gowa\Laravel\Models\GowaWebhookCall::where('device_id', 'dev-audit')->first();

    expect($call)->not->toBeNull()
        ->and($call->instance_id)->toBe($instance->id)
        ->and($call->event)->toBe('message')
        ->and($call->payload)->toEqual($payload)
        ->and($call->processed)->toBeTrue();

    // Verify relation on instance
    expect($instance->webhookCalls()->count())->toBe(1);
});

test('record_calls can be disabled via config', function () {
    config(['gowa.webhook.record_calls' => false]);

    $instance = GowaInstance::create([
        'name'      => 'No Record Instance',
        'device_id' => 'dev-no-record',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.NO.RECORD',
            'chat_id' => '5511999997777@s.whatsapp.net',
            'body'    => 'Do not record in DB',
        ],
    ];

    $response = postWebhook('dev-no-record', $payload);
    $response->assertOk();

    expect(\Gowa\Laravel\Models\GowaWebhookCall::where('device_id', 'dev-no-record')->count())->toBe(0);
});

test('audit call keeps the real device_id, url and headers when the device has no instance row', function () {
    withGlobalSecret();

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.STATELESS.1',
            'chat_id' => '5511999995555@s.whatsapp.net',
            'body'    => 'From an unregistered device',
        ],
    ];

    postWebhook('dev-stateless', $payload)->assertOk();

    $call = \Gowa\Laravel\Models\GowaWebhookCall::where('device_id', 'dev-stateless')->first();

    expect($call)->not->toBeNull()
        ->and($call->instance_id)->toBeNull()
        ->and($call->event)->toBe('message')
        ->and($call->url)->toContain('/webhooks/gowa/dev-stateless')
        ->and($call->headers)->toHaveKey('x-hub-signature-256')
        ->and($call->payload)->toEqual($payload);
});

test('audit call does not store credential headers', function () {
    withGlobalSecret();

    $call = null;
    postWebhook('dev-headers', ['event' => 'unknown'])->assertOk();

    $call = \Gowa\Laravel\Models\GowaWebhookCall::where('device_id', 'dev-headers')->first();

    expect($call->headers)->not->toHaveKey('authorization')
        ->and($call->headers)->not->toHaveKey('cookie');
});

test('stateless webhook dispatches events with a null instanceId and the real deviceId', function () {
    Event::fake();
    withGlobalSecret();

    postWebhook('dev-no-row', [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.NOROW.1',
            'chat_id' => '5511999994444@s.whatsapp.net',
            'body'    => 'hi',
        ],
    ])->assertOk();

    Event::assertDispatched(GowaMessageReceived::class, function ($e) {
        return $e->instanceId === null && $e->deviceId === 'dev-no-row';
    });
});

class ExplodingGowaMessage extends GowaMessage
{
    public static function updateOrCreate(array $attributes, array $values = [])
    {
        throw new RuntimeException('sync blew up');
    }
}

test('a listener that throws marks the audit call as failed with the exception', function () {
    GowaInstance::create([
        'name'      => 'Failing Instance',
        'device_id' => 'dev-explode',
        'status'    => GowaInstanceStatus::Open,
    ]);

    config(['gowa.models.message' => ExplodingGowaMessage::class]);

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.EXPLODE.1',
            'chat_id' => '5511999993333@s.whatsapp.net',
            'body'    => 'this will fail to sync',
        ],
    ];

    try {
        postWebhook('dev-explode', $payload);
    } catch (Throwable) {
        // the sync queue driver rethrows; the audit row is what we care about
    }

    $call = \Gowa\Laravel\Models\GowaWebhookCall::where('device_id', 'dev-explode')->first();

    expect($call)->not->toBeNull()
        ->and($call->processed)->toBeFalse()
        ->and($call->exception)->toContain('sync blew up');
});

test('incoming webhook parses numeric unix epoch timestamp correctly', function () {
    $instance = GowaInstance::create([
        'name'      => 'Timestamp Test',
        'device_id' => 'dev-time-1',
        'status'    => GowaInstanceStatus::Open,
    ]);

    // 1725368400 is 2024-09-03
    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'        => 'WAMID.TIME.001',
            'chat_id'   => '5511999990001@s.whatsapp.net',
            'body'      => 'Numeric timestamp test',
            'timestamp' => '1725368400',
        ],
    ];

    postWebhook('dev-time-1', $payload)->assertOk();

    $message = GowaMessage::where('message_id', 'WAMID.TIME.001')->first();

    expect($message)->not->toBeNull()
        ->and($message->sent_at->year)->toBe(2024)
        ->and($message->sent_at->month)->toBe(9)
        ->and($message->sent_at->day)->toBe(3);
});

test('contact_name is preserved when subsequent messages lack display name', function () {
    $instance = GowaInstance::create([
        'name'      => 'Contact Name Test',
        'device_id' => 'dev-contact-name',
        'status'    => GowaInstanceStatus::Open,
    ]);

    // 1st message with sender_display_name
    postWebhook('dev-contact-name', [
        'event'   => 'message',
        'payload' => [
            'id'                  => 'WAMID.NAME.001',
            'chat_id'             => '5511999990002@s.whatsapp.net',
            'sender_display_name' => 'Alice Bob',
            'body'                => 'Hello with name',
        ],
    ])->assertOk();

    $conv = GowaConversation::where('contact_jid', '5511999990002@s.whatsapp.net')->first();
    expect($conv->contact_name)->toBe('Alice Bob');

    // 2nd message without sender_display_name
    postWebhook('dev-contact-name', [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.NAME.002',
            'chat_id' => '5511999990002@s.whatsapp.net',
            'body'    => 'Hello without name',
        ],
    ])->assertOk();

    $conv->refresh();
    expect($conv->contact_name)->toBe('Alice Bob');
});

test('device echo message is synced as outbound and sent', function () {
    $instance = GowaInstance::create([
        'name'      => 'Echo Test',
        'device_id' => 'dev-echo-1',
        'status'    => GowaInstanceStatus::Open,
    ]);

    postWebhook('dev-echo-1', [
        'event'   => 'message',
        'payload' => [
            'id'         => 'WAMID.ECHO.001',
            'chat_id'    => '5511999990003@s.whatsapp.net',
            'is_from_me' => true,
            'body'       => 'Sent from my physical phone',
        ],
    ])->assertOk();

    $msg = GowaMessage::where('message_id', 'WAMID.ECHO.001')->first();

    expect($msg)->not->toBeNull()
        ->and($msg->direction)->toBe(GowaMessageDirection::Outbound)
        ->and($msg->status)->toBe(GowaMessageStatus::Sent)
        ->and($msg->body)->toBe('Sent from my physical phone');
});

test('late delivered ack does not regress message status from read to delivered', function () {
    $instance = GowaInstance::create([
        'name'      => 'Ack Order Test',
        'device_id' => 'dev-ack-order',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $message = GowaMessage::create([
        'instance_id' => $instance->id,
        'message_id'  => 'OUT.ORDER.001',
        'direction'   => GowaMessageDirection::Outbound,
        'status'      => GowaMessageStatus::Sent,
        'type'        => 'text',
        'body'        => 'Testing out of order acks',
        'sent_at'     => now(),
    ]);

    // 1. Read ack arrives first
    postWebhook('dev-ack-order', [
        'event'   => 'message.ack',
        'payload' => [
            'ids'          => ['OUT.ORDER.001'],
            'receipt_type' => 'read',
            'chat_id'      => '5511999990004@s.whatsapp.net',
        ],
    ])->assertOk();

    $message->refresh();
    expect($message->status)->toBe(GowaMessageStatus::Read)
        ->and($message->read_at)->not->toBeNull()
        ->and($message->delivered_at)->not->toBeNull();

    // 2. Late delivered ack arrives
    postWebhook('dev-ack-order', [
        'event'   => 'message.ack',
        'payload' => [
            'ids'          => ['OUT.ORDER.001'],
            'receipt_type' => 'delivered',
            'chat_id'      => '5511999990004@s.whatsapp.net',
        ],
    ])->assertOk();

    $message->refresh();
    // Status must remain Read and not regress to Delivered
    expect($message->status)->toBe(GowaMessageStatus::Read);
});

test('outbound message via Notification channel auto-creates message in database', function () {
    $instance = GowaInstance::create([
        'name'      => 'Notif Channel Sync',
        'device_id' => 'dev-notif-sync',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('dev-notif-sync', '5511999998888', 'Hello from Notification!', null)
        ->andReturn(new SentMessage('NOTIF.SENT.123'));

    $channel = new \Gowa\Laravel\Notifications\GowaChannel($client);

    $notifiable = new class () {
        public function routeNotificationForGowa(): array
        {
            return ['device' => 'dev-notif-sync', 'to' => '5511999998888'];
        }
    };

    $notification = new class () extends \Illuminate\Notifications\Notification {
        public function toGowa(mixed $notifiable): \Gowa\Laravel\Notifications\GowaMessage
        {
            return \Gowa\Laravel\Notifications\GowaMessage::create('Hello from Notification!');
        }
    };

    $channel->send($notifiable, $notification);

    $msg = GowaMessage::where('message_id', 'NOTIF.SENT.123')->first();

    expect($msg)->not->toBeNull()
        ->and($msg->instance_id)->toBe($instance->id)
        ->and($msg->direction)->toBe(GowaMessageDirection::Outbound)
        ->and($msg->status)->toBe(GowaMessageStatus::Sent)
        ->and($msg->body)->toBe('Hello from Notification!');
});

test('outbound message auto_sync can be disabled via config', function () {
    config(['gowa.auto_sync.outbound' => false]);

    $instance = GowaInstance::create([
        'name'      => 'No Outbound Sync',
        'device_id' => 'dev-no-out-sync',
        'status'    => GowaInstanceStatus::Open,
    ]);

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('dev-no-out-sync', '5511999993333', 'No sync test', null)
        ->andReturn(new SentMessage('NO.SYNC.999'));

    Gowa::from('dev-no-out-sync')->to('5511999993333')->text('No sync test')->send($client);

    expect(GowaMessage::where('message_id', 'NO.SYNC.999')->count())->toBe(0);
});
