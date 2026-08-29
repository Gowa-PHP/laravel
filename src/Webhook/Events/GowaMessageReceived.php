<?php

declare(strict_types=1);

namespace Gowa\Laravel\Webhook\Events;

use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GowaMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $instanceId,
        public readonly IncomingMessage $message,
        public readonly array $raw,
    ) {}
}
