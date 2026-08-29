<?php

declare(strict_types=1);

use Gowa\Sdk\GowaClient;

beforeEach(function () {
    config()->set('gowa.base_url', 'https://gowa.example.com');
    config()->set('gowa.username', 'admin');
    config()->set('gowa.password', 'secret');
});

test('service provider binds GowaClient as singleton', function () {
    $a = app(GowaClient::class);
    $b = app(GowaClient::class);

    expect($a)->toBeInstanceOf(GowaClient::class)
        ->and($a)->toBe($b);
});

test('config is merged with package defaults', function () {
    expect(config('gowa.timeout'))->toBe(15)
        ->and(config('gowa.webhook.path'))->toBe('webhooks/gowa')
        ->and(config('gowa.teams.enabled'))->toBeFalse();
});

test('config models point to package models by default', function () {
    expect(config('gowa.models.instance'))->toBe(\Gowa\Laravel\Models\GowaInstance::class)
        ->and(config('gowa.models.conversation'))->toBe(\Gowa\Laravel\Models\GowaConversation::class)
        ->and(config('gowa.models.message'))->toBe(\Gowa\Laravel\Models\GowaMessage::class);
});
