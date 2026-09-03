<?php

declare(strict_types=1);

namespace Gowa\Laravel\Webhook\Listeners;

use Gowa\Laravel\Enums\GowaMessageStatus;
use Gowa\Laravel\Models\GowaWebhookCall;
use Gowa\Laravel\Webhook\Events\GowaMessageAck;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncMessageAck implements ShouldQueue
{
    public function handle(GowaMessageAck $event): void
    {
        if (! config('gowa.auto_sync.inbound', true) || ! config('gowa.webhook.auto_sync', true)) {
            return;
        }

        // A device with no local instance row has no messages to ack.
        if ($event->instanceId === null) {
            return;
        }

        try {
            $this->sync($event);
        } catch (\Throwable $e) {
            GowaWebhookCall::markFailed($event->webhookCallId, $e);

            throw $e;
        }
    }

    private function sync(GowaMessageAck $event): void
    {
        $messageModel = config('gowa.models.message', \Gowa\Laravel\Models\GowaMessage::class);
        $ack = $event->ack;

        if ($ack->isRead()) {
            // Populate delivered_at if not already populated
            $messageModel::where('instance_id', $event->instanceId)
                ->whereIn('message_id', $ack->messageIds)
                ->whereNull('delivered_at')
                ->update(['delivered_at' => now()]);

            $messageModel::where('instance_id', $event->instanceId)
                ->whereIn('message_id', $ack->messageIds)
                ->update([
                    'status'  => GowaMessageStatus::Read,
                    'read_at' => now(),
                ]);
        } elseif ($ack->isDelivered()) {
            // Prevent regressing a message that has already been read
            $messageModel::where('instance_id', $event->instanceId)
                ->whereIn('message_id', $ack->messageIds)
                ->where('status', '!=', GowaMessageStatus::Read)
                ->update([
                    'status'       => GowaMessageStatus::Delivered,
                    'delivered_at' => now(),
                ]);
        }
    }
}
