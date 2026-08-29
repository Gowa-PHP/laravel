<?php

declare(strict_types=1);

use Gowa\Laravel\Notifications\GowaMessage;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\GowaClient;

/** @return array{device: string, to: string} */
function testRoute(): array
{
    return ['device' => 'device-01', 'to' => '5511999999999'];
}

function fakeSent(): SentMessage
{
    return new SentMessage('fake-id');
}

function fakeMedia(MediaType $type = MediaType::Image): MediaPayload
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'fake-bytes');
    rewind($stream);

    return new MediaPayload(
        type: $type,
        upload: MediaUpload::fromStream($stream, 'image/jpeg', 'photo.jpg'),
    );
}

test('create with text sends via sendText', function () {
    $msg = GowaMessage::create('Hello!');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('device-01', '5511999999999', 'Hello!', null)
        ->andReturn(fakeSent());

    $msg->send($client, testRoute());
});

test('fluent text method', function () {
    $msg = GowaMessage::create()->text('Fluent text');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('device-01', '5511999999999', 'Fluent text', null)
        ->andReturn(fakeSent());

    $msg->send($client, testRoute());
});

test('replyTo is forwarded to sendText', function () {
    $msg = GowaMessage::create('Reply!')->replyTo('MSGID-123');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendText')
        ->once()
        ->with('device-01', '5511999999999', 'Reply!', 'MSGID-123')
        ->andReturn(fakeSent());

    $msg->send($client, testRoute());
});

test('media is sent via sendMedia', function () {
    $media = fakeMedia();
    $msg = GowaMessage::create()->media($media, 'A caption');

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendMedia')
        ->once()
        ->with('device-01', '5511999999999', $media, null)
        ->andReturn(fakeSent());

    $msg->send($client, testRoute());
});

test('location is sent via sendLocation', function () {
    $location = new LocationPayload(latitude: -23.5505, longitude: -46.6333);
    $msg = GowaMessage::create()->location($location);

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendLocation')
        ->once()
        ->with('device-01', '5511999999999', $location)
        ->andReturn(fakeSent());

    $msg->send($client, testRoute());
});

test('location takes priority over text', function () {
    $location = new LocationPayload(latitude: -23.5505, longitude: -46.6333);
    $msg = GowaMessage::create('Text too')->location($location);

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendLocation')->once()->andReturn(fakeSent());
    $client->shouldNotReceive('sendText');

    $msg->send($client, testRoute());
});

test('media takes priority over text', function () {
    $msg = GowaMessage::create('Text too')->media(fakeMedia(MediaType::Document));

    $client = Mockery::mock(GowaClient::class);
    $client->shouldReceive('sendMedia')->once()->andReturn(fakeSent());
    $client->shouldNotReceive('sendText');

    $msg->send($client, testRoute());
});
