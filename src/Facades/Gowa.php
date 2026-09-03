<?php

declare(strict_types=1);

namespace Gowa\Laravel\Facades;

use Gowa\Laravel\PendingMessage;
use Gowa\Sdk\GowaClient;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Gowa\Sdk\GowaClient
 * @see \Gowa\Laravel\PendingMessage
 *
 * @method static \Gowa\Laravel\PendingMessage to(string $to)
 * @method static \Gowa\Laravel\PendingMessage from(string $deviceId)
 * @method static \Gowa\Laravel\PendingMessage disk(string $disk)
 * @method static \Gowa\Laravel\PendingMessage replyTo(string $messageId)
 * @method static \Gowa\Sdk\Dto\SentMessage sendText(string $deviceId, string $to, string $text, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendMedia(string $deviceId, string $to, \Gowa\Sdk\Dto\MediaPayload $media, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendLocation(string $deviceId, string $to, \Gowa\Sdk\Dto\LocationPayload $location, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendContacts(string $deviceId, string $to, array $contacts, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendReaction(string $deviceId, string $to, string $providerMessageId, string $emoji)
 * @method static \Gowa\Sdk\Dto\SentMessage sendLink(string $deviceId, string $to, string $link, ?string $caption = null, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendPoll(string $deviceId, string $to, string $question, array $options, int $maxSelections = 1, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendSticker(string $deviceId, string $to, \Gowa\Sdk\Dto\MediaUpload $upload, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage forwardMessage(string $deviceId, string $to, string $providerMessageId)
 * @method static \Gowa\Sdk\Dto\SentMessage editMessage(string $deviceId, string $to, string $providerMessageId, string $newText)
 * @method static void revokeMessage(string $deviceId, string $to, string $providerMessageId)
 * @method static void deleteMessage(string $deviceId, string $to, string $providerMessageId)
 * @method static void starMessage(string $deviceId, string $to, string $providerMessageId, bool $star = true)
 * @method static void markPlayed(string $deviceId, string $to, string $providerMessageId)
 * @method static void markRead(string $deviceId, string $to, string $providerMessageId, bool $withTyping = false)
 * @method static \Gowa\Sdk\Dto\RemoteMedia|null describeMedia(string $deviceId, string $to, string $providerMessageId)
 * @method static void downloadMedia(string $mediaUrl, string $destinationPath)
 * @method static bool isConfigured()
 * @method static \Gowa\Sdk\Dto\Device createDevice(string $deviceId, string $webhookUrl, string $webhookSecret, array $events)
 * @method static \Gowa\Sdk\Dto\Pairing startQrPairing(string $deviceId)
 * @method static \Gowa\Sdk\Dto\Pairing startCodePairing(string $deviceId, string $phone)
 * @method static \Gowa\Sdk\Dto\Device|null device(string $deviceId)
 * @method static void logout(string $deviceId)
 * @method static array fetchQrImage(string $qrLink)
 * @method static \Gowa\Sdk\Dto\Avatar|null avatar(string $deviceId, string $phone)
 */
class Gowa extends Facade
{
    public static bool $runsMigrations = true;

    public static function ignoreMigrations(): void
    {
        static::$runsMigrations = false;
    }

    protected static function getFacadeAccessor(): string
    {
        return GowaClient::class;
    }

    public static function to(string $to): PendingMessage
    {
        return (new PendingMessage())->to($to);
    }

    public static function from(string $deviceId): PendingMessage
    {
        return (new PendingMessage())->from($deviceId);
    }

    public static function disk(string $disk): PendingMessage
    {
        return (new PendingMessage())->disk($disk);
    }

    public static function replyTo(string $messageId): PendingMessage
    {
        return (new PendingMessage())->replyTo($messageId);
    }
}
