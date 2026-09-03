<?php

declare(strict_types=1);

use Gowa\Laravel\Tests\TestCase;
use Illuminate\Testing\TestResponse;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Configure (and return) the global webhook secret, mirroring the GOWA server's
 * `WHATSAPP_WEBHOOK_SECRET`, which every delivery is signed with when the device
 * has no secret of its own.
 */
function withGlobalSecret(string $secret = 'global-hmac-secret'): string
{
    config(['gowa.webhook.secret' => $secret]);

    return $secret;
}

function signWebhook(string $payload, string $secret): string
{
    return 'sha256=' . hash_hmac('sha256', $payload, $secret);
}

/**
 * POST a signed webhook the way the GOWA server does: JSON body plus an
 * `X-Hub-Signature-256` header. Falls back to the configured global secret,
 * setting one when the test has not.
 */
function postWebhook(string $deviceId, array $payload, ?string $secret = null): TestResponse
{
    $secret ??= (string) (config('gowa.webhook.secret') ?: withGlobalSecret());
    $body = (string) json_encode($payload);

    return test()->call(
        'POST',
        "/webhooks/gowa/{$deviceId}",
        [],
        [],
        [],
        [
            'HTTP_X_HUB_SIGNATURE_256' => signWebhook($body, $secret),
            'CONTENT_TYPE'             => 'application/json',
        ],
        $body,
    );
}
