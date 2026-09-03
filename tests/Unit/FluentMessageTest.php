<?php

declare(strict_types=1);

use Gowa\Laravel\Enums\GowaInstanceStatus;
use Gowa\Laravel\Facades\Gowa;
use Gowa\Laravel\Models\GowaInstance;
use Gowa\Laravel\Notifications\GowaMessage;
use Gowa\Laravel\PendingMessage;
use Gowa\Sdk\Dto\ContactCard;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\GowaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('Gowa::to returns a PendingMessage instance', function () {
    $pending = Gowa::to('5511999998888');

    expect($pending)->toBeInstanceOf(PendingMessage::class);
});

test('Gowa::from returns a PendingMessage with deviceId set', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('custom-dev', '5511999998888', 'Hello!', null)
        ->andReturn(new SentMessage('msg-001'));

    $pending = Gowa::from('custom-dev')->to('5511999998888')->text('Hello!');
    $res = $pending->send($client);

    expect($res->providerMessageId)->toBe('msg-001');
});

test('PendingMessage sends text with replyTo and default config device', function () {
    config(['gowa.default_device_id' => 'config-dev']);

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('config-dev', '5511999998888', 'Reply msg', 'REPLY-ID')
        ->andReturn(new SentMessage('msg-002'));

    $res = Gowa::to('5511999998888')
        ->replyTo('REPLY-ID')
        ->text('Reply msg')
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-002');
});

test('PendingMessage falls back to database GowaInstance when deviceId not provided', function () {
    config(['gowa.default_device_id' => null]);

    GowaInstance::create([
        'device_id' => 'db-device-uuid',
        'name' => 'Support Desk',
        'status' => GowaInstanceStatus::Open,
    ]);

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('db-device-uuid', '5511999998888', 'From DB device', null)
        ->andReturn(new SentMessage('msg-003'));

    $res = Gowa::to('5511999998888')->text('From DB device')->send($client);

    expect($res->providerMessageId)->toBe('msg-003');
});

test('PendingMessage throws when no recipient provided', function () {
    $pending = new PendingMessage();
    $pending->text('Hello');

    $pending->send();
})->throws(InvalidArgumentException::class, 'No recipient provided');

test('PendingMessage throws when no deviceId provided and none configured or in database', function () {
    config(['gowa.default_device_id' => null]);

    $pending = new PendingMessage();
    $pending->to('5511999998888')->text('Hello');

    $pending->send();
})->throws(InvalidArgumentException::class, 'No GOWA device ID provided');

test('PendingMessage throws when no message content set', function () {
    $pending = new PendingMessage();
    $pending->from('dev-1')->to('5511999998888');

    $pending->send();
})->throws(InvalidArgumentException::class, 'No message content specified');

test('sends image from external URL', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendMedia')
        ->once()
        ->withArgs(function ($dev, $to, MediaPayload $media, $replyTo) {
            return $dev === 'dev-1'
                && $to === '5511999998888'
                && $media->type === MediaType::Image
                && $media->caption === 'Nice photo'
                && $media->upload?->source === 'https://example.com/banner.png';
        })
        ->andReturn(new SentMessage('msg-media'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->image('https://example.com/banner.png', 'Nice photo')
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-media');
});

test('sends document from Laravel Storage fake disk', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('invoices/invoice_123.pdf', 'dummy-pdf-content');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendMedia')
        ->once()
        ->withArgs(function ($dev, $to, MediaPayload $media, $replyTo) {
            return $dev === 'dev-1'
                && $to === '5511999998888'
                && $media->type === MediaType::Document
                && $media->caption === 'Invoice'
                && $media->upload?->filename === 'Fatura.pdf';
        })
        ->andReturn(new SentMessage('msg-doc'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->disk('s3')
        ->document('invoices/invoice_123.pdf', filename: 'Fatura.pdf', caption: 'Invoice')
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-doc');
});

test('sends image from default Storage disk via fallback resolution', function () {
    Storage::fake();
    Storage::put('avatars/user.jpg', 'fake-jpg-data');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendMedia')
        ->once()
        ->withArgs(function ($dev, $to, MediaPayload $media, $replyTo) {
            return $media->type === MediaType::Image
                && $media->upload?->filename === 'user.jpg';
        })
        ->andReturn(new SentMessage('msg-storage-default'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->image('avatars/user.jpg')
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-storage-default');
});

test('sends voice note PTT', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'ogg-audio-data');
    rewind($stream);

    $upload = MediaUpload::fromStream($stream, 'audio/ogg', 'voice.ogg');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendMedia')
        ->once()
        ->withArgs(function ($dev, $to, MediaPayload $media, $replyTo) {
            return $media->type === MediaType::Audio
                && $media->voice === true;
        })
        ->andReturn(new SentMessage('msg-voice'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->voice($upload)
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-voice');
});

test('sends location', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendLocation')
        ->once()
        ->withArgs(function ($dev, $to, LocationPayload $loc) {
            return $loc->latitude === -23.5505 && $loc->longitude === -46.6333;
        })
        ->andReturn(new SentMessage('msg-loc'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->location(-23.5505, -46.6333)
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-loc');
});

test('sends single contact and multiple contacts', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendContacts')
        ->once()
        ->withArgs(function ($dev, $to, array $contacts) {
            return count($contacts) === 1
                && $contacts[0] instanceof ContactCard
                && $contacts[0]->name === 'John Doe';
        })
        ->andReturn(new SentMessage('msg-contact'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->contact('John Doe', '5511999991111')
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-contact');
});

test('sends link with preview caption', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendLink')
        ->once()
        ->with('dev-1', '5511999998888', 'https://antigravity.google', 'AGY Docs', null)
        ->andReturn(new SentMessage('msg-link'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->link('https://antigravity.google', 'AGY Docs')
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-link');
});

test('sends poll', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendPoll')
        ->once()
        ->with('dev-1', '5511999998888', 'Best day?', ['Friday', 'Saturday'], 1, null)
        ->andReturn(new SentMessage('msg-poll'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->poll('Best day?', ['Friday', 'Saturday'], 1)
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-poll');
});

test('sends sticker', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'webp-bytes');
    rewind($stream);

    $upload = MediaUpload::fromStream($stream, 'image/webp', 'sticker.webp');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendSticker')
        ->once()
        ->with('dev-1', '5511999998888', $upload, null)
        ->andReturn(new SentMessage('msg-sticker'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->sticker($upload)
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-sticker');
});

test('sends emoji reaction', function () {
    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendReaction')
        ->once()
        ->with('dev-1', '5511999998888', 'target-msg-id', '👍')
        ->andReturn(new SentMessage('msg-react'));

    $res = Gowa::from('dev-1')
        ->to('5511999998888')
        ->reaction('target-msg-id', '👍')
        ->send($client);

    expect($res->providerMessageId)->toBe('msg-react');
});

test('direct actions on PendingMessage: markRead, revoke, star, edit, forward', function () {
    $client = Mockery::mock(GowaClient::class);

    $client->shouldReceive('markRead')
        ->once()
        ->with('dev-1', '5511999998888', 'msg-1', false);

    $client->shouldReceive('revokeMessage')
        ->once()
        ->with('dev-1', '5511999998888', 'msg-1');

    $client->shouldReceive('starMessage')
        ->once()
        ->with('dev-1', '5511999998888', 'msg-1', true);

    $client->shouldReceive('editMessage')
        ->once()
        ->with('dev-1', '5511999998888', 'msg-1', 'Edited text')
        ->andReturn(new SentMessage('msg-1-edited'));

    $client->shouldReceive('forwardMessage')
        ->once()
        ->with('dev-1', '5511999998888', 'msg-1')
        ->andReturn(new SentMessage('msg-1-fwd'));

    $pending = Gowa::from('dev-1')->to('5511999998888');

    // Bind client in container or pass via reflection/setter
    $appClient = app();
    $appClient->instance(GowaClient::class, $client);

    $pending->markRead('msg-1');
    $pending->revoke('msg-1');
    $pending->star('msg-1');
    $editRes = $pending->edit('msg-1', 'Edited text');
    $fwdRes = $pending->forward('msg-1');

    expect($editRes->providerMessageId)->toBe('msg-1-edited')
        ->and($fwdRes->providerMessageId)->toBe('msg-1-fwd');
});

test('GowaMessage notification builder works with storage document', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('reports/report.pdf', 'dummy-pdf-content');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendMedia')
        ->once()
        ->withArgs(function ($dev, $to, MediaPayload $media, $replyTo) {
            return $dev === 'device-01'
                && $to === '5511999999999'
                && $media->type === MediaType::Document
                && $media->caption === 'Monthly Report'
                && $media->upload?->filename === 'Relatorio.pdf';
        })
        ->andReturn(new SentMessage('notif-msg'));

    $msg = GowaMessage::create()
        ->disk('s3')
        ->document('reports/report.pdf', filename: 'Relatorio.pdf', caption: 'Monthly Report');

    $msg->send($client, ['device' => 'device-01', 'to' => '5511999999999']);
});
