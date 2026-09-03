<?php

declare(strict_types=1);

namespace Gowa\Laravel\Webhook\Listeners;

use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogWebhookRequest implements ShouldQueue
{
    public function handle(GowaWebhookReceived $event): void
    {
        // The audit row is written by the controller, before this listener is queued.
        if (config('gowa.webhook.log_requests', false)) {
            $channel = config('gowa.webhook.log_channel');
            $message = "GOWA Webhook Received [{$event->event->value}] for device {$event->deviceId}";
            $context = [
                'event'       => $event->event->value,
                'instance_id' => $event->instanceId,
                'device_id'   => $event->deviceId,
                'payload'     => $event->raw,
            ];

            if (! empty($channel)) {
                Log::channel($channel)->info($message, $context);
            } else {
                Log::info($message, $context);
            }
        }
    }
}
