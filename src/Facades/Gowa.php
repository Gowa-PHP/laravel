<?php

declare(strict_types=1);

namespace Gowa\Laravel\Facades;

use Gowa\Sdk\GowaClient;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Gowa\Sdk\GowaClient
 *
 * @method static \Gowa\Sdk\Dto\SentMessage sendText(string $to, string $text, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendMedia(string $to, \Gowa\Sdk\Dto\MediaPayload $media, ?string $caption = null, ?string $replyTo = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendLocation(string $to, \Gowa\Sdk\Dto\LocationPayload $location)
 * @method static \Gowa\Sdk\Dto\SentMessage sendLink(string $to, string $url, ?string $caption = null)
 * @method static \Gowa\Sdk\Dto\SentMessage sendPoll(string $to, string $question, array $options, int $maxAnswers = 1)
 * @method static \Gowa\Sdk\Dto\SentMessage editMessage(string $to, string $messageId, string $newText)
 * @method static \Gowa\Sdk\Dto\SentMessage revokeMessage(string $to, string $messageId)
 * @method static void markRead(string $to, string $messageId)
 */
class Gowa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GowaClient::class;
    }
}
