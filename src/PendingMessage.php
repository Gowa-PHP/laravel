<?php

declare(strict_types=1);

namespace Gowa\Laravel;

use Gowa\Laravel\Concerns\BuildsMessagePayload;
use Gowa\Laravel\Enums\GowaInstanceStatus;
use Gowa\Sdk\Dto\SentMessage;
use Gowa\Sdk\GowaClient;
use InvalidArgumentException;

class PendingMessage
{
    use BuildsMessagePayload;

    protected ?string $to = null;
    protected ?string $deviceId = null;

    public function __construct(
        protected ?GowaClient $client = null,
    ) {}

    public function usingClient(GowaClient $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function from(string $deviceId): self
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function send(?GowaClient $client = null): SentMessage
    {
        $to = $this->resolveTo();
        $deviceId = $this->resolveDeviceId();
        $client ??= $this->client ?? app(GowaClient::class);

        $sentMessage = match (true) {
            $this->location !== null => $this->replyTo !== null
                ? $client->sendLocation($deviceId, $to, $this->location, $this->replyTo)
                : $client->sendLocation($deviceId, $to, $this->location),
            $this->sticker !== null => $client->sendSticker($deviceId, $to, $this->sticker, $this->replyTo),
            $this->media !== null => $client->sendMedia($deviceId, $to, $this->media, $this->replyTo),
            $this->contacts !== null => $client->sendContacts($deviceId, $to, $this->contacts, $this->replyTo),
            $this->poll !== null => $client->sendPoll(
                $deviceId,
                $to,
                $this->poll['question'],
                $this->poll['options'],
                $this->poll['maxSelections'],
                $this->replyTo,
            ),
            $this->link !== null => $client->sendLink($deviceId, $to, $this->link['url'], $this->link['caption'], $this->replyTo),
            $this->reaction !== null => $client->sendReaction($deviceId, $to, $this->reaction['messageId'], $this->reaction['emoji']),
            $this->text !== null => $client->sendText($deviceId, $to, $this->text, $this->replyTo),
            default => throw new InvalidArgumentException('No message content specified to send. Call text(), image(), document(), etc.'),
        };

        $this->recordOutboundMessage($deviceId, $to, $sentMessage);

        return $sentMessage;
    }

    public function markRead(string $messageId, bool $withTyping = false): void
    {
        $client = $this->client ?? app(GowaClient::class);
        $client->markRead($this->resolveDeviceId(), $this->resolveTo(), $messageId, $withTyping);
    }

    public function markPlayed(string $messageId): void
    {
        $client = $this->client ?? app(GowaClient::class);
        $client->markPlayed($this->resolveDeviceId(), $this->resolveTo(), $messageId);
    }

    public function revoke(string $messageId): void
    {
        $client = $this->client ?? app(GowaClient::class);
        $client->revokeMessage($this->resolveDeviceId(), $this->resolveTo(), $messageId);
    }

    public function delete(string $messageId): void
    {
        $client = $this->client ?? app(GowaClient::class);
        $client->deleteMessage($this->resolveDeviceId(), $this->resolveTo(), $messageId);
    }

    public function star(string $messageId, bool $star = true): void
    {
        $client = $this->client ?? app(GowaClient::class);
        $client->starMessage($this->resolveDeviceId(), $this->resolveTo(), $messageId, $star);
    }

    public function edit(string $messageId, string $newText): SentMessage
    {
        $client = $this->client ?? app(GowaClient::class);

        return $client->editMessage($this->resolveDeviceId(), $this->resolveTo(), $messageId, $newText);
    }

    public function forward(string $messageId): SentMessage
    {
        $client = $this->client ?? app(GowaClient::class);

        return $client->forwardMessage($this->resolveDeviceId(), $this->resolveTo(), $messageId);
    }

    protected function resolveDeviceId(): string
    {
        if (! empty($this->deviceId)) {
            return $this->deviceId;
        }

        $configuredDefault = config('gowa.default_device_id');
        if (! empty($configuredDefault)) {
            return (string) $configuredDefault;
        }

        $instanceModel = config('gowa.models.instance', \Gowa\Laravel\Models\GowaInstance::class);
        if (class_exists($instanceModel)) {
            $instance = $instanceModel::query()
                ->where('status', GowaInstanceStatus::Open->value)
                ->first() ?? $instanceModel::query()->first();

            if ($instance?->device_id) {
                return (string) $instance->device_id;
            }
        }

        throw new InvalidArgumentException('No GOWA device ID provided. Specify one using ->from($deviceId) or configure gowa.default_device_id.');
    }

    protected function resolveTo(): string
    {
        if (empty($this->to)) {
            throw new InvalidArgumentException('No recipient provided. Specify one using Gowa::to($phone) or ->to($phone).');
        }

        return $this->to;
    }
}
