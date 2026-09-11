<?php

declare(strict_types=1);

use Gowa\Laravel\Facades\Gowa;
use Gowa\Laravel\Models\GowaConversation;
use Gowa\Laravel\Models\GowaMessage;
use Gowa\Laravel\Models\GowaWebhookCall;
use Gowa\Laravel\Notifications\GowaChannel;
use Gowa\Laravel\Notifications\GowaMessage as GowaNotificationMessage;
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\GowaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('gowa.stateless', true);
    config()->set('gowa.default_device_id', 'stateless-device-uuid');
    withGlobalSecret('global-stateless-secret');
});

test('Gowa::isStateless and Gowa::isDriverOnly reflect configuration', function () {
    expect(Gowa::isStateless())->toBeTrue()
        ->and(Gowa::isDriverOnly())->toBeTrue();

    config(['gowa.stateless' => false, 'gowa.driver_only' => false]);
    expect(Gowa::isStateless())->toBeFalse()
        ->and(Gowa::isDriverOnly())->toBeFalse();
});

test('Gowa::to sends message without touching database in stateless mode', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('stateless-device-uuid', '5511999998888', 'Hello Stateless!', null)
        ->andReturn(new SentMessage('wamid-stl-001'));

    $sent = Gowa::to('5511999998888')
        ->text('Hello Stateless!')
        ->send($client);

    expect($sent->providerMessageId)->toBe('wamid-stl-001');

    // Confirm no DB rows created
    expect(GowaMessage::count())->toBe(0)
        ->and(GowaConversation::count())->toBe(0);
});

test('Gowa::from allows custom device ID without database in stateless mode', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('custom-tenant-device', '5511999998888', 'Custom device text', null)
        ->andReturn(new SentMessage('wamid-stl-002'));

    $sent = Gowa::from('custom-tenant-device')
        ->to('5511999998888')
        ->text('Custom device text')
        ->send($client);

    expect($sent->providerMessageId)->toBe('wamid-stl-002');
    expect(GowaMessage::count())->toBe(0);
});

test('GowaChannel sends notifications without touching database in stateless mode', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('stateless-device-uuid', '5511999997777', 'Notification text', null)
        ->andReturn(new SentMessage('wamid-stl-notif-001'));

    $channel = new GowaChannel($client);

    $notifiable = new class () {
        public function routeNotificationForGowa(): string
        {
            return '5511999997777';
        }
    };

    $notification = new class () extends Notification {
        public function toGowa(mixed $notifiable): GowaNotificationMessage
        {
            return GowaNotificationMessage::create('Notification text');
        }
    };

    $channel->send($notifiable, $notification);

    expect(GowaMessage::count())->toBe(0)
        ->and(GowaConversation::count())->toBe(0);
});

test('PendingMessage throws when no device provided in stateless mode without querying DB', function () {
    config(['gowa.default_device_id' => null]);

    expect(fn() => Gowa::to('5511999998888')->text('Test')->send())
        ->toThrow(InvalidArgumentException::class, 'No GOWA device ID provided.');
});

test('stateless webhook accepts valid signed delivery and dispatches stateless events', function () {
    Event::fake([
        GowaWebhookReceived::class,
        GowaMessageReceived::class,
    ]);

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'                  => 'WAMID.STL.100',
            'chat_id'             => '5511999990000@s.whatsapp.net',
            'sender_display_name' => 'Bob Stateless',
            'body'                => 'Hello in stateless mode',
            'timestamp'           => '2026-09-01T12:00:00Z',
        ],
    ];

    $response = postWebhook('any-stateless-device', $payload, 'global-stateless-secret');
    $response->assertOk();

    // Verify events were dispatched with null instanceId and null webhookCallId
    Event::assertDispatched(GowaWebhookReceived::class, function (GowaWebhookReceived $event) {
        return $event->instanceId === null
            && $event->deviceId === 'any-stateless-device'
            && $event->webhookCallId === null;
    });

    Event::assertDispatched(GowaMessageReceived::class, function (GowaMessageReceived $event) {
        return $event->instanceId === null
            && $event->deviceId === 'any-stateless-device'
            && $event->webhookCallId === null
            && $event->message->id === 'WAMID.STL.100'
            && $event->message->body === 'Hello in stateless mode';
    });

    // Verify no records inserted in package tables
    expect(GowaWebhookCall::count())->toBe(0)
        ->and(GowaMessage::count())->toBe(0)
        ->and(GowaConversation::count())->toBe(0);
});

test('stateless webhook rejects invalid HMAC signature', function () {
    $payload = [
        'event'   => 'message',
        'payload' => ['id' => 'WAMID.FAIL', 'body' => 'fail'],
    ];

    $response = postWebhook('any-stateless-device', $payload, 'wrong-secret');
    $response->assertForbidden();
});

test('stateless webhook correctly handles incoming images and media helpers', function () {
    /** @var GowaMessageReceived|null $capturedEvent */
    $capturedEvent = null;

    Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) use (&$capturedEvent) {
        $capturedEvent = $event;
    });

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'                  => 'WAMID.MEDIA.IMAGE.001',
            'chat_id'             => '5511999990000@s.whatsapp.net',
            'sender_display_name' => 'Alice Photo',
            'image'               => [
                'url'      => 'https://gowa.example.com/media/download/img_001.jpg',
                'mimetype' => 'image/jpeg',
                'caption'  => 'Foto da fatura',
            ],
        ],
    ];

    $response = postWebhook('bot-device', $payload, 'global-stateless-secret');
    $response->assertOk();

    expect($capturedEvent)->not->toBeNull()
        ->and($capturedEvent->message->type)->toBe('image')
        ->and($capturedEvent->message->body)->toBe('Foto da fatura')
        ->and($capturedEvent->isMedia())->toBeTrue()
        ->and($capturedEvent->mediaUrl())->toBe('https://gowa.example.com/media/download/img_001.jpg')
        ->and($capturedEvent->mediaMime())->toBe('image/jpeg')
        ->and($capturedEvent->isLocation())->toBeFalse();
});

test('stateless webhook correctly handles incoming documents and mediaFilename', function () {
    /** @var GowaMessageReceived|null $capturedEvent */
    $capturedEvent = null;

    Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) use (&$capturedEvent) {
        $capturedEvent = $event;
    });

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'       => 'WAMID.MEDIA.DOC.001',
            'chat_id'  => '5511999990000@s.whatsapp.net',
            'document' => [
                'url'      => 'https://gowa.example.com/media/download/contract.pdf',
                'mimetype' => 'application/pdf',
                'filename' => 'contrato_prestacao_servico.pdf',
                'caption'  => 'Segue o contrato em anexo',
            ],
        ],
    ];

    $response = postWebhook('bot-device', $payload, 'global-stateless-secret');
    $response->assertOk();

    expect($capturedEvent)->not->toBeNull()
        ->and($capturedEvent->message->type)->toBe('document')
        ->and($capturedEvent->message->body)->toBe('Segue o contrato em anexo')
        ->and($capturedEvent->isMedia())->toBeTrue()
        ->and($capturedEvent->mediaUrl())->toBe('https://gowa.example.com/media/download/contract.pdf')
        ->and($capturedEvent->mediaMime())->toBe('application/pdf')
        ->and($capturedEvent->mediaFilename())->toBe('contrato_prestacao_servico.pdf');
});

test('stateless webhook correctly handles audio voice notes and isVoiceNote', function () {
    /** @var GowaMessageReceived|null $capturedEvent */
    $capturedEvent = null;

    Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) use (&$capturedEvent) {
        $capturedEvent = $event;
    });

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.MEDIA.VOICE.001',
            'chat_id' => '5511999990000@s.whatsapp.net',
            'audio'   => [
                'url'      => 'https://gowa.example.com/media/download/ptt.ogg',
                'mimetype' => 'audio/ogg; codecs=opus',
                'ptt'      => true,
            ],
        ],
    ];

    $response = postWebhook('bot-device', $payload, 'global-stateless-secret');
    $response->assertOk();

    expect($capturedEvent)->not->toBeNull()
        ->and($capturedEvent->message->type)->toBe('audio')
        ->and($capturedEvent->isMedia())->toBeTrue()
        ->and($capturedEvent->isVoiceNote())->toBeTrue()
        ->and($capturedEvent->mediaUrl())->toBe('https://gowa.example.com/media/download/ptt.ogg');
});

test('stateless webhook correctly handles incoming location and location helper', function () {
    /** @var GowaMessageReceived|null $capturedEvent */
    $capturedEvent = null;

    Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) use (&$capturedEvent) {
        $capturedEvent = $event;
    });

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'       => 'WAMID.LOCATION.001',
            'chat_id'  => '5511999990000@s.whatsapp.net',
            'location' => [
                'latitude'  => -23.55052,
                'longitude' => -46.633308,
                'name'      => 'Praça da Sé',
                'address'   => 'São Paulo, SP, Brasil',
            ],
        ],
    ];

    $response = postWebhook('bot-device', $payload, 'global-stateless-secret');
    $response->assertOk();

    expect($capturedEvent)->not->toBeNull()
        ->and($capturedEvent->message->type)->toBe('location')
        ->and($capturedEvent->isMedia())->toBeFalse()
        ->and($capturedEvent->isLocation())->toBeTrue()
        ->and($capturedEvent->isStaticLocation())->toBeTrue()
        ->and($capturedEvent->isLiveLocation())->toBeFalse()
        ->and($capturedEvent->location())->toEqual([
            'latitude'  => -23.55052,
            'longitude' => -46.633308,
            'name'      => 'Praça da Sé',
            'address'   => 'São Paulo, SP, Brasil',
        ])
        ->and($capturedEvent->locationCoordinates())->toBeInstanceOf(\Gowa\Sdk\Dto\LocationPayload::class)
        ->and($capturedEvent->locationCoordinates()->latitude)->toBe(-23.55052)
        ->and($capturedEvent->locationCoordinates()->longitude)->toBe(-46.633308);
});

test('stateless webhook correctly handles protobuf degreesLatitude format for static location', function () {
    /** @var GowaMessageReceived|null $capturedEvent */
    $capturedEvent = null;

    Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) use (&$capturedEvent) {
        $capturedEvent = $event;
    });

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'       => 'WAMID.LOCATION.PROTO.001',
            'chat_id'  => '5511999990000@s.whatsapp.net',
            'location' => [
                'degreesLatitude'  => -23.55052,
                'degreesLongitude' => -46.633308,
                'name'             => 'Avenida Paulista',
            ],
        ],
    ];

    $response = postWebhook('bot-device', $payload, 'global-stateless-secret');
    $response->assertOk();

    expect($capturedEvent)->not->toBeNull()
        ->and($capturedEvent->isLocation())->toBeTrue()
        ->and($capturedEvent->isStaticLocation())->toBeTrue()
        ->and($capturedEvent->isLiveLocation())->toBeFalse()
        ->and($capturedEvent->locationCoordinates()->latitude)->toBe(-23.55052)
        ->and($capturedEvent->locationCoordinates()->longitude)->toBe(-46.633308);
});

test('stateless webhook correctly handles realtime live location (live_location)', function () {
    /** @var GowaMessageReceived|null $capturedEvent */
    $capturedEvent = null;

    Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) use (&$capturedEvent) {
        $capturedEvent = $event;
    });

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'            => 'WAMID.LIVELOCATION.001',
            'chat_id'       => '5511999990000@s.whatsapp.net',
            'live_location' => [
                'degreesLatitude'  => -22.906847,
                'degreesLongitude' => -43.172896,
                'accuracyInMeters' => 12,
                'speedInMps'       => 4.5,
                'caption'          => 'A caminho do destino',
            ],
        ],
    ];

    $response = postWebhook('bot-device', $payload, 'global-stateless-secret');
    $response->assertOk();

    expect($capturedEvent)->not->toBeNull()
        ->and($capturedEvent->isLocation())->toBeTrue()
        ->and($capturedEvent->isStaticLocation())->toBeFalse()
        ->and($capturedEvent->isLiveLocation())->toBeTrue()
        ->and($capturedEvent->liveLocationData())->toEqual([
            'degreesLatitude'  => -22.906847,
            'degreesLongitude' => -43.172896,
            'accuracyInMeters' => 12,
            'speedInMps'       => 4.5,
            'caption'          => 'A caminho do destino',
        ])
        ->and($capturedEvent->locationCoordinates())->toBeInstanceOf(\Gowa\Sdk\Dto\LocationPayload::class)
        ->and($capturedEvent->locationCoordinates()->latitude)->toBe(-22.906847)
        ->and($capturedEvent->locationCoordinates()->longitude)->toBe(-43.172896);
});

test('works completely without package database tables (dropped tables test)', function () {
    // Drop all package tables completely
    Schema::dropIfExists('gowa_webhook_calls');
    Schema::dropIfExists('gowa_messages');
    Schema::dropIfExists('gowa_conversations');
    Schema::dropIfExists('gowa_instances');

    // 1. Outbound sending without tables
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('stateless-device-uuid', '5511999998888', 'Stateless text', null)
        ->andReturn(new SentMessage('wamid-no-tables-001'));

    $sent = Gowa::to('5511999998888')
        ->text('Stateless text')
        ->send($client);

    expect($sent->providerMessageId)->toBe('wamid-no-tables-001');

    // 2. Notification channel without tables
    $channel = new GowaChannel($client);
    $client->shouldReceive('sendText')
        ->once()
        ->with('stateless-device-uuid', '5511999998888', 'Stateless notif', null)
        ->andReturn(new SentMessage('wamid-no-tables-notif'));

    $notifiable = new class () {
        public function routeNotificationForGowa(): string
        {
            return '5511999998888';
        }
    };
    $notification = new class () extends Notification {
        public function toGowa(mixed $notifiable): GowaNotificationMessage
        {
            return GowaNotificationMessage::create('Stateless notif');
        }
    };

    // Must not throw QueryException
    $channel->send($notifiable, $notification);

    // 3. Webhook delivery without tables
    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'                  => 'WAMID.NO_TABLES.001',
            'chat_id'             => '5511999990000@s.whatsapp.net',
            'sender_display_name' => 'Stateless User',
            'body'                => 'Hello with no tables',
        ],
    ];

    // Must return 200 OK and not throw QueryException
    $response = postWebhook('no-table-device', $payload, 'global-stateless-secret');
    $response->assertOk();
});

test('stateless webhook correctly handles polls, events, and fluent routing on message', function () {
    /** @var GowaMessageReceived|null $capturedEvent */
    $capturedEvent = null;

    Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) use (&$capturedEvent) {
        $capturedEvent = $event;
    });

    $payload = [
        'event'   => 'message',
        'payload' => [
            'id'      => 'WAMID.POLL.001',
            'chat_id' => '5511999990000@s.whatsapp.net',
            'poll'    => [
                'question' => 'Qual o melhor dia para a reunião?',
                'options'  => ['Segunda', 'Quarta', 'Sexta'],
            ],
        ],
    ];

    postWebhook('bot-device', $payload, 'global-stateless-secret')->assertOk();

    expect($capturedEvent)->not->toBeNull()
        ->and($capturedEvent->isPoll())->toBeTrue()
        ->and($capturedEvent->poll())->toBeInstanceOf(\Gowa\Sdk\Dto\PollPayload::class)
        ->and($capturedEvent->poll()->question)->toBe('Qual o melhor dia para a reunião?')
        ->and($capturedEvent->poll()->options)->toBe(['Segunda', 'Quarta', 'Sexta']);

    // Fluent routing on $capturedEvent->message
    $handledQuestion = null;
    $capturedEvent->message
        ->whenPoll(function (\Gowa\Sdk\Dto\PollPayload $poll) use (&$handledQuestion) {
            $handledQuestion = $poll->question;
        })
        ->whenText(function () {
            throw new Exception('Should not reach whenText');
        });

    expect($handledQuestion)->toBe('Qual o melhor dia para a reunião?');
});
