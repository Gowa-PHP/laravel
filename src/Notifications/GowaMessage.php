<?php

declare(strict_types=1);

namespace Gowa\Laravel\Notifications;

use Gowa\Laravel\Concerns\BuildsMessagePayload;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\GowaClient;
use InvalidArgumentException;

/**
 * Fluent message builder for the GOWA notification channel.
 *
 * Usage in your Notification:
 *
 * ```php
 * public function toGowa(mixed $notifiable): GowaMessage
 * {
 *     return GowaMessage::create('Hello!');
 * }
 * ```
 *
 * Or using media and Storage attachments:
 *
 * ```php
 * public function toGowa(mixed $notifiable): GowaMessage
 * {
 *     return GowaMessage::create()
 *         ->disk('s3')
 *         ->document('invoices/invoice.pdf', filename: 'Fatura.pdf', caption: 'Sua fatura')
 *         ->replyTo($this->messageId);
 * }
 * ```
 */
class GowaMessage
{
    use BuildsMessagePayload;

    public static function create(?string $text = null): self
    {
        $instance = new self();
        $instance->text = $text;

        return $instance;
    }

    /**
     * @param array{device: string, to: string} $route
     */
    public function send(GowaClient $client, array $route): SentMessage
    {
        $deviceId = $route['device'];
        $to = $route['to'];

        $sentMessage = match (true) {
            $this->location !== null => $this->replyTo !== null
                ? $client->sendLocation($deviceId, $to, $this->location, $this->replyTo)
                : $client->sendLocation($deviceId, $to, $this->location),
            $this->sticker !== null  => $client->sendSticker($deviceId, $to, $this->sticker, $this->replyTo),
            $this->media !== null    => $client->sendMedia($deviceId, $to, $this->media, $this->replyTo),
            $this->contacts !== null => $client->sendContacts($deviceId, $to, $this->contacts, $this->replyTo),
            $this->poll !== null     => $client->sendPoll(
                $deviceId,
                $to,
                $this->poll['question'],
                $this->poll['options'],
                $this->poll['maxSelections'],
                $this->replyTo,
            ),
            $this->link !== null     => $client->sendLink($deviceId, $to, $this->link['url'], $this->link['caption'], $this->replyTo),
            $this->reaction !== null => $client->sendReaction($deviceId, $to, $this->reaction['messageId'], $this->reaction['emoji']),
            $this->text !== null     => $client->sendText($deviceId, $to, $this->text, $this->replyTo),
            default                  => throw new InvalidArgumentException('No notification content specified to send. Call text(), image(), document(), etc.'),
        };

        $this->recordOutboundMessage($deviceId, $to, $sentMessage);

        return $sentMessage;
    }
}
