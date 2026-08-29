<?php

declare(strict_types=1);

namespace Gowa\Laravel\Notifications;

use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\GowaClient;

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
 * Your notifiable must implement:
 *
 * ```php
 * public function routeNotificationForGowa(): array
 * {
 *     return ['device' => $this->gowa_device_id, 'to' => $this->phone];
 * }
 * ```
 */
class GowaMessage
{
    private ?string $text = null;
    private ?MediaPayload $media = null;
    private ?LocationPayload $location = null;
    private ?string $replyTo = null;

    public static function create(?string $text = null): self
    {
        $instance = new self;
        $instance->text = $text;

        return $instance;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function media(MediaPayload $media): self
    {
        $this->media = $media;

        return $this;
    }

    public function location(LocationPayload $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function replyTo(string $messageId): self
    {
        $this->replyTo = $messageId;

        return $this;
    }

    /**
     * @param array{device: string, to: string} $route
     */
    public function send(GowaClient $client, array $route): void
    {
        $deviceId = $route['device'];
        $to = $route['to'];

        if ($this->location !== null) {
            $client->sendLocation($deviceId, $to, $this->location);

            return;
        }

        if ($this->media !== null) {
            $client->sendMedia($deviceId, $to, $this->media, $this->replyTo);

            return;
        }

        if ($this->text !== null) {
            $client->sendText($deviceId, $to, $this->text, $this->replyTo);
        }
    }
}
