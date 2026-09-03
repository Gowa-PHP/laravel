<?php

declare(strict_types=1);

use Gowa\Laravel\Models\GowaInstance;
use Gowa\Sdk\GowaClient;
use Illuminate\Http\Request;

beforeEach(function () {
    config()->set('gowa.base_url', 'https://gowa.example.com');
    config()->set('gowa.username', 'admin');
    config()->set('gowa.password', 'secret');
});

test('client() returns GowaClient', function () {
    $instance = new GowaInstance();

    expect($instance->client())->toBeInstanceOf(GowaClient::class);
});

test('verifyWebhookSignature returns false when no secret is configured anywhere', function () {
    config(['gowa.webhook.secret' => null]);

    $instance = new GowaInstance(['webhook_secret' => null]);
    $request = Request::create('/webhook', 'POST', content: '{}');

    expect($instance->verifyWebhookSignature($request))->toBeFalse();
});

test('verifyWebhookSignature falls back to the global secret when the instance has none', function () {
    $secret = 'global-hmac-secret';
    $payload = '{"event":"message"}';
    config(['gowa.webhook.secret' => $secret]);

    $instance = new GowaInstance(['webhook_secret' => null]);
    $request = Request::create('/webhook', 'POST', content: $payload);
    $request->headers->set('X-Hub-Signature-256', 'sha256=' . hash_hmac('sha256', $payload, $secret));

    expect($instance->verifyWebhookSignature($request))->toBeTrue();
});

test('verifyWebhookSignature prefers the instance secret over the global one', function () {
    $payload = '{"event":"message"}';
    config(['gowa.webhook.secret' => 'global-hmac-secret']);

    $instance = new GowaInstance(['webhook_secret' => 'device-secret']);
    $request = Request::create('/webhook', 'POST', content: $payload);
    $request->headers->set('X-Hub-Signature-256', 'sha256=' . hash_hmac('sha256', $payload, 'global-hmac-secret'));

    expect($instance->verifyWebhookSignature($request))->toBeFalse();
});

test('verifyWebhookSignature returns true for valid HMAC', function () {
    $secret = 'my-test-secret';
    $payload = '{"event":"message"}';
    $sig = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    $instance = new GowaInstance(['webhook_secret' => $secret]);
    $request = Request::create('/webhook', 'POST', content: $payload);
    $request->headers->set('X-Gowa-Signature', $sig);

    expect($instance->verifyWebhookSignature($request))->toBeTrue();
});

test('verifyWebhookSignature returns false for invalid HMAC', function () {
    $instance = new GowaInstance(['webhook_secret' => 'correct-secret']);
    $request = Request::create('/webhook', 'POST', content: '{"event":"message"}');
    $request->headers->set('X-Gowa-Signature', 'sha256=invalidsignature');

    expect($instance->verifyWebhookSignature($request))->toBeFalse();
});

test('verifyWebhookSignature returns false when signature header missing', function () {
    $instance = new GowaInstance(['webhook_secret' => 'some-secret']);
    $request = Request::create('/webhook', 'POST', content: '{}');

    expect($instance->verifyWebhookSignature($request))->toBeFalse();
});

test('isConnected checks status enum', function () {
    $instance = new GowaInstance(['status' => 'open']);

    expect($instance->status->isConnected())->toBeTrue();
});

test('non-open status is not connected', function () {
    foreach (['created', 'connecting', 'close'] as $status) {
        $instance = new GowaInstance(['status' => $status]);
        expect($instance->status->isConnected())->toBeFalse();
    }
});
