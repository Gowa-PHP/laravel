<?php

declare(strict_types=1);

namespace Gowa\Laravel\Notifications;

use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\GowaClient;

/**
 * Fluent message builder for the GOWA notification channel.
 *
 * @example
 * GowaMessage::create('Hello!')
 * GowaMessage::create()->media($payload, 'caption')
 * GowaMessage::create()->location($locationPayload)
 */
class GowaMessage
{
    private ?string $text = null;
    private ?MediaPayload $media = null;
    private ?string $caption = null;
    private ?LocationPayload $location = null;
    private ?string $replyTo = null;

    public static function create(string $text = ''): self
    {
        $instance = new self;

        if ($text !== '') {
            $instance->text = $text;
        }

        return $instance;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function media(MediaPayload $media, ?string $caption = null): self
    {
        $this->media = $media;
        $this->caption = $caption;

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

    public function send(GowaClient $client, string $to): void
    {
        if ($this->location !== null) {
            $client->sendLocation($to, $this->location);

            return;
        }

        if ($this->media !== null) {
            $client->sendMedia($to, $this->media, $this->caption, $this->replyTo);

            return;
        }

        if ($this->text !== null) {
            $client->sendText($to, $this->text, $this->replyTo);
        }
    }
}
