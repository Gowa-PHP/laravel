<?php

declare(strict_types=1);

namespace Gowa\Laravel\Webhook\Events;

use Gowa\Sdk\Webhook\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired for every incoming GOWA webhook, before type-specific events.
 * Useful for logging, auditing, or handling event types not yet covered.
 */
class GowaWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?int $instanceId,
        public readonly string $deviceId,
        public readonly Event $event,
        public readonly mixed $data,
        public readonly array $raw,
        public readonly ?string $url = null,
        public readonly array $headers = [],
        public readonly ?int $webhookCallId = null,
    ) {}
}
