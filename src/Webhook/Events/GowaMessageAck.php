<?php

declare(strict_types=1);

namespace Gowa\Laravel\Webhook\Events;

use Gowa\Sdk\Webhook\Dto\IncomingAck;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GowaMessageAck
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $instanceId,
        public readonly IncomingAck $ack,
        public readonly array $raw,
    ) {}
}
