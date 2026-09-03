<?php

declare(strict_types=1);

namespace Gowa\Laravel\Concerns;

use Gowa\Laravel\Enums\GowaMessageDirection;
use Gowa\Laravel\Enums\GowaMessageStatus;
use Gowa\Sdk\Dto\ContactCard;
use Gowa\Sdk\Dto\LocationPayload;
use Gowa\Sdk\Dto\MediaPayload;
use Gowa\Sdk\Dto\MediaType;
use Gowa\Sdk\Dto\MediaUpload;
use Gowa\Sdk\Dto\SentMessage;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

trait BuildsMessagePayload
{
    protected ?string $disk = null;
    protected ?string $text = null;
    protected ?MediaPayload $media = null;
    protected ?MediaUpload $sticker = null;
    protected ?LocationPayload $location = null;
    /** @var list<ContactCard>|null */
    protected ?array $contacts = null;
    /** @var array{question: string, options: list<string>, maxSelections: int}|null */
    protected ?array $poll = null;
    /** @var array{url: string, caption: ?string}|null */
    protected ?array $link = null;
    /** @var array{messageId: string, emoji: string}|null */
    protected ?array $reaction = null;
    protected ?string $replyTo = null;

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function replyTo(string $messageId): static
    {
        $this->replyTo = $messageId;

        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function image(
        mixed $file,
        ?string $caption = null,
        ?string $disk = null,
    ): static {
        $upload = $this->resolveUpload($file, mimeType: 'image/jpeg', disk: $disk);
        $this->media = new MediaPayload(MediaType::Image, $upload, $caption);

        return $this;
    }

    public function video(
        mixed $file,
        ?string $caption = null,
        ?string $disk = null,
    ): static {
        $upload = $this->resolveUpload($file, mimeType: 'video/mp4', disk: $disk);
        $this->media = new MediaPayload(MediaType::Video, $upload, $caption);

        return $this;
    }

    public function audio(
        mixed $file,
        ?string $disk = null,
    ): static {
        $upload = $this->resolveUpload($file, mimeType: 'audio/mp3', disk: $disk);
        $this->media = new MediaPayload(MediaType::Audio, $upload, voice: false);

        return $this;
    }

    public function voice(
        mixed $file,
        ?string $disk = null,
    ): static {
        $upload = $this->resolveUpload($file, mimeType: 'audio/ogg', disk: $disk);
        $this->media = new MediaPayload(MediaType::Audio, $upload, voice: true);

        return $this;
    }

    public function document(
        mixed $file,
        ?string $filename = null,
        ?string $caption = null,
        ?string $disk = null,
    ): static {
        $upload = $this->resolveUpload($file, filename: $filename, mimeType: 'application/pdf', disk: $disk);
        $this->media = new MediaPayload(MediaType::Document, $upload, $caption);

        return $this;
    }

    public function sticker(
        mixed $file,
        ?string $disk = null,
    ): static {
        $this->sticker = $this->resolveUpload($file, mimeType: 'image/webp', disk: $disk);

        return $this;
    }

    public function location(
        float|LocationPayload $latitude,
        ?float $longitude = null,
    ): static {
        if ($latitude instanceof LocationPayload) {
            $this->location = $latitude;
        } elseif ($longitude !== null) {
            $this->location = new LocationPayload((float) $latitude, (float) $longitude);
        } else {
            throw new InvalidArgumentException('Longitude must be provided when passing numeric latitude.');
        }

        return $this;
    }

    public function contact(string $name, string $phone): static
    {
        $this->contacts = [
            new ContactCard($name, [['phone' => $phone]]),
        ];

        return $this;
    }

    /**
     * @param list<ContactCard|array{name: string, phones?: list<array{phone: string}>, phone?: string}> $contacts
     */
    public function contacts(array $contacts): static
    {
        $cards = [];
        foreach ($contacts as $item) {
            if ($item instanceof ContactCard) {
                $cards[] = $item;
            } elseif (is_array($item)) {
                $name = (string) ($item['name'] ?? '');
                /** @var list<array{phone: string}> $phones */
                $phones = $item['phones'] ?? (isset($item['phone']) ? [['phone' => (string) $item['phone']]] : []);
                $cards[] = new ContactCard($name, $phones);
            }
        }

        $this->contacts = $cards;

        return $this;
    }

    public function link(string $url, ?string $caption = null): static
    {
        $this->link = [
            'url'     => $url,
            'caption' => $caption,
        ];

        return $this;
    }

    /**
     * @param list<string> $options
     */
    public function poll(string $question, array $options, int $maxSelections = 1): static
    {
        $this->poll = [
            'question'      => $question,
            'options'       => array_values($options),
            'maxSelections' => $maxSelections,
        ];

        return $this;
    }

    public function reaction(string $messageId, string $emoji): static
    {
        $this->reaction = [
            'messageId' => $messageId,
            'emoji'     => $emoji,
        ];

        return $this;
    }

    public function media(MediaPayload $media): static
    {
        $this->media = $media;

        return $this;
    }

    protected function resolveUpload(
        mixed $file,
        ?string $filename = null,
        ?string $mimeType = null,
        ?string $disk = null,
    ): MediaUpload {
        if ($file instanceof MediaUpload) {
            return $file;
        }

        if (is_resource($file)) {
            return MediaUpload::fromStream(
                $file,
                $mimeType ?? 'application/octet-stream',
                $filename ?? 'file',
            );
        }

        if (! is_string($file)) {
            throw new InvalidArgumentException('Media file must be a string (path/URL), resource, or MediaUpload instance.');
        }

        $targetDisk = $disk ?? $this->disk;

        // 1. Explicit disk configured on message or method
        if ($targetDisk !== null) {
            $storage = Storage::disk($targetDisk);
            $stream = $storage->readStream($file);

            if (! is_resource($stream)) {
                throw new InvalidArgumentException("Could not read file [{$file}] from storage disk [{$targetDisk}].");
            }

            $detectedMime = $storage->mimeType($file) ?: ($mimeType ?? 'application/octet-stream');
            $resolvedFilename = $filename ?? basename($file);

            return MediaUpload::fromStream($stream, $detectedMime, $resolvedFilename);
        }

        // 2. URL (http / https)
        if (filter_var($file, FILTER_VALIDATE_URL)) {
            return MediaUpload::fromUrl($file, $mimeType, $filename);
        }

        // 3. Local filesystem path
        if (file_exists($file)) {
            return MediaUpload::fromPath($file, $mimeType, $filename);
        }

        // 4. Default Storage disk fallback if file exists there
        if (Storage::exists($file)) {
            $storage = Storage::disk();
            $stream = $storage->readStream($file);

            if (is_resource($stream)) {
                $detectedMime = $storage->mimeType($file) ?: ($mimeType ?? 'application/octet-stream');
                $resolvedFilename = $filename ?? basename($file);

                return MediaUpload::fromStream($stream, $detectedMime, $resolvedFilename);
            }
        }

        throw new InvalidArgumentException("Media file not found: [{$file}]. Provide a valid local path, URL, Storage path, or stream resource.");
    }

    public function recordOutboundMessage(string $deviceId, string $to, SentMessage $sentMessage): void
    {
        if (! config('gowa.auto_sync.outbound', true)) {
            return;
        }

        $instanceModel = config('gowa.models.instance', \Gowa\Laravel\Models\GowaInstance::class);
        $conversationModel = config('gowa.models.conversation', \Gowa\Laravel\Models\GowaConversation::class);
        $messageModel = config('gowa.models.message', \Gowa\Laravel\Models\GowaMessage::class);

        if (! class_exists($instanceModel) || ! class_exists($conversationModel) || ! class_exists($messageModel)) {
            return;
        }

        /** @var \Gowa\Laravel\Models\GowaInstance|null $instance */
        $instance = $instanceModel::where('device_id', $deviceId)->first();
        if ($instance === null) {
            return;
        }

        $chatId = str_contains($to, '@') ? $to : $to . '@s.whatsapp.net';
        $phone = explode('@', $chatId)[0];

        $conversationValues = [
            'contact_phone'   => $phone,
            'last_message_at' => now(),
        ];

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

        $body = $this->text
            ?? $this->media?->caption
            ?? ($this->poll ? $this->poll['question'] : null)
            ?? ($this->link ? $this->link['url'] : null);

        $type = match (true) {
            $this->media !== null    => $this->media->type->value,
            $this->location !== null => 'location',
            $this->poll !== null     => 'poll',
            $this->contacts !== null => 'contacts',
            $this->sticker !== null  => 'sticker',
            $this->link !== null     => 'link',
            $this->reaction !== null => 'reaction',
            default                  => 'text',
        };

        $mediaUrl = null;
        $mediaMime = null;
        if ($this->media !== null) {
            $mediaMime = $this->media->upload?->mimeType;
            if (is_string($this->media->upload?->source) && filter_var($this->media->upload->source, FILTER_VALIDATE_URL)) {
                $mediaUrl = $this->media->upload->source;
            }
        }

        $messageValues = [
            'conversation_id' => $conversation->id,
            'direction'       => GowaMessageDirection::Outbound,
            'status'          => GowaMessageStatus::Sent,
            'type'            => $type,
            'body'            => $body,
            'media_url'       => $mediaUrl,
            'media_mime'      => $mediaMime,
            'reply_to'        => $this->replyTo,
            'sent_at'         => now(),
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
                'message_id'  => $sentMessage->providerMessageId,
            ],
            $messageValues,
        );
    }
}
