<?php

declare(strict_types=1);

namespace Gowa\Laravel\Webhook\Listeners;

use Gowa\Laravel\Enums\GowaMessageDirection;
use Gowa\Laravel\Enums\GowaMessageStatus;
use Gowa\Laravel\Models\GowaWebhookCall;
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncIncomingMessage implements ShouldQueue
{
    public function handle(GowaMessageReceived $event): void
    {
        if (! config('gowa.auto_sync.inbound', true) || ! config('gowa.webhook.auto_sync', true)) {
            return;
        }

        try {
            $this->sync($event);
        } catch (\Throwable $e) {
            GowaWebhookCall::markFailed($event->webhookCallId, $e);

            throw $e;
        }
    }

    private function sync(GowaMessageReceived $event): void
    {

        $instanceModel = config('gowa.models.instance', \Gowa\Laravel\Models\GowaInstance::class);
        $conversationModel = config('gowa.models.conversation', \Gowa\Laravel\Models\GowaConversation::class);
        $messageModel = config('gowa.models.message', \Gowa\Laravel\Models\GowaMessage::class);

        /** @var \Gowa\Laravel\Models\GowaInstance|null $instance */
        $instance = $event->instanceId !== null ? $instanceModel::find($event->instanceId) : null;
        if ($instance === null) {
            return;
        }

        $instance->update(['last_seen_at' => now()]);

        $incoming = $event->message;
        $chatId = $incoming->chatId;
        $phone = $incoming->phone;
        $senderName = $incoming->senderName;

        $conversationValues = [
            'contact_phone'   => $phone,
            'last_message_at' => now(),
        ];

        // Protect existing contact_name from being overwritten by null or device echo
        if (! empty($senderName) && ! $incoming->isEcho) {
            $conversationValues['contact_name'] = $senderName;
        }

        if (config('gowa.teams.enabled', false)) {
            $teamFk = config('gowa.teams.foreign_key', 'team_id');
            if (isset($instance->{$teamFk})) {
                $conversationValues[$teamFk] = $instance->{$teamFk};
            }
        }

        /** @var \Gowa\Laravel\Models\GowaConversation $conversation */
        $conversation = $conversationModel::updateOrCreate(
            [
                'instance_id' => $instance->id,
                'contact_jid' => $chatId,
            ],
            $conversationValues,
        );

        $sentAt = match (true) {
            empty($incoming->timestamp)      => now(),
            is_numeric($incoming->timestamp) => \Carbon\Carbon::createFromTimestamp((int) $incoming->timestamp),
            default                          => \Carbon\Carbon::parse($incoming->timestamp),
        };

        $direction = $incoming->isEcho ? GowaMessageDirection::Outbound : GowaMessageDirection::Inbound;
        $status = $incoming->isEcho ? GowaMessageStatus::Sent : GowaMessageStatus::Delivered;

        $payloadBody = (array) ($event->raw['payload'] ?? $event->raw);
        $mediaData = $payloadBody[$incoming->type] ?? null;
        $mediaUrl = is_array($mediaData) ? ($mediaData['url'] ?? null) : null;
        $mediaMime = is_array($mediaData) ? ($mediaData['mimetype'] ?? $mediaData['mime_type'] ?? null) : null;

        $messageValues = [
            'conversation_id' => $conversation->id,
            'direction'       => $direction,
            'status'          => $status,
            'type'            => $incoming->type,
            'body'            => $incoming->body,
            'media_url'       => $mediaUrl,
            'media_mime'      => $mediaMime,
            'reply_to'        => $incoming->quotedMessageId,
            'meta'            => $event->raw,
            'sent_at'         => $sentAt,
            'delivered_at'    => $incoming->isEcho ? null : now(),
        ];

        if (config('gowa.teams.enabled', false)) {
            $teamFk = config('gowa.teams.foreign_key', 'team_id');
            if (isset($instance->{$teamFk})) {
                $messageValues[$teamFk] = $instance->{$teamFk};
            }
        }

        $messageModel::updateOrCreate(
            [
                'instance_id' => $instance->id,
                'message_id'  => $incoming->id,
            ],
            $messageValues,
        );
    }
}
