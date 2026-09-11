<?php

declare(strict_types=1);

namespace Gowa\Laravel\Webhook\Events;

use Gowa\Sdk\Dto\EventPayload;
use Gowa\Sdk\Dto\LiveLocationPayload;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\OrderPayload;
use Gowa\Sdk\Dto\PollPayload;
use Gowa\Sdk\Webhook\Dto\IncomingMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GowaMessageReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ?int $instanceId,
        public readonly string $deviceId,
        public readonly IncomingMessage $message,
        public readonly array $raw,
        public readonly ?int $webhookCallId = null,
    ) {}

    public function isMedia(): bool
    {
        return in_array($this->message->type, ['image', 'video', 'audio', 'document', 'sticker'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mediaData(): ?array
    {
        $payloadBody = (array) ($this->raw['payload'] ?? $this->raw);

        return isset($payloadBody[$this->message->type]) && is_array($payloadBody[$this->message->type])
            ? $payloadBody[$this->message->type]
            : null;
    }

    public function mediaUrl(): ?string
    {
        $data = $this->mediaData();

        return is_string($data['url'] ?? null) ? $data['url'] : null;
    }

    public function mediaMime(): ?string
    {
        $data = $this->mediaData();
        $mime = $data['mimetype'] ?? $data['mime_type'] ?? null;

        return is_string($mime) ? $mime : null;
    }

    public function mediaFilename(): ?string
    {
        $data = $this->mediaData();

        return is_string($data['filename'] ?? null) ? $data['filename'] : null;
    }

    public function isVoiceNote(): bool
    {
        $data = $this->mediaData();

        return $this->message->type === 'audio' && (bool) ($data['ptt'] ?? false);
    }

    public function isLocation(): bool
    {
        return $this->isStaticLocation() || $this->isLiveLocation();
    }

    public function isStaticLocation(): bool
    {
        if ($this->message->type === 'location') {
            return true;
        }

        $payloadBody = (array) ($this->raw['payload'] ?? $this->raw);

        return isset($payloadBody['location']) && is_array($payloadBody['location']);
    }

    public function isLiveLocation(): bool
    {
        if ($this->message->type === 'live_location') {
            return true;
        }

        return $this->liveLocationData() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function location(): ?array
    {
        $payloadBody = (array) ($this->raw['payload'] ?? $this->raw);

        if (isset($payloadBody['location']) && is_array($payloadBody['location'])) {
            return $payloadBody['location'];
        }

        return $this->liveLocationData();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function liveLocationData(): ?array
    {
        $payloadBody = (array) ($this->raw['payload'] ?? $this->raw);

        if (isset($payloadBody['live_location']) && is_array($payloadBody['live_location'])) {
            return $payloadBody['live_location'];
        }

        if (isset($payloadBody['liveLocation']) && is_array($payloadBody['liveLocation'])) {
            return $payloadBody['liveLocation'];
        }

        return null;
    }

    public function locationCoordinates(): ?LocationPayload
    {
        $loc = $this->location();
        if ($loc === null) {
            return null;
        }

        $lat = $loc['degreesLatitude'] ?? $loc['latitude'] ?? $loc['lat'] ?? null;
        $lng = $loc['degreesLongitude'] ?? $loc['longitude'] ?? $loc['lng'] ?? $loc['lon'] ?? $loc['long'] ?? null;

        if ($lat === null || $lng === null) {
            return null;
        }

        return new LocationPayload(
            latitude: (float) $lat,
            longitude: (float) $lng,
        );
    }

    public function isContact(): bool
    {
        return in_array($this->message->type, ['contact', 'contacts'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contactData(): ?array
    {
        $payloadBody = (array) ($this->raw['payload'] ?? $this->raw);

        return isset($payloadBody['contact']) && is_array($payloadBody['contact'])
            ? $payloadBody['contact']
            : (isset($payloadBody['contacts']) && is_array($payloadBody['contacts']) ? $payloadBody['contacts'] : null);
    }

    public function liveLocation(): ?LiveLocationPayload
    {
        return $this->message->liveLocation();
    }

    public function isPoll(): bool
    {
        return $this->message->isPoll();
    }

    public function poll(): ?PollPayload
    {
        return $this->message->poll();
    }

    public function isEvent(): bool
    {
        return $this->message->isEvent();
    }

    public function eventData(): ?EventPayload
    {
        return $this->message->event();
    }

    public function isOrder(): bool
    {
        return $this->message->isOrder();
    }

    public function order(): ?OrderPayload
    {
        return $this->message->order();
    }
}
