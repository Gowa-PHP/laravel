<?php

declare(strict_types=1);

namespace Gowa\Laravel\Notifications;

use Gowa\Sdk\GowaClient;
use Illuminate\Notifications\Notification;

/**
 * Laravel Notification Channel for GOWA WhatsApp.
 *
 * Your Notification must implement `toGowa(mixed $notifiable): GowaMessage`.
 * Your notifiable must implement `routeNotificationForGowa(): string`
 * returning the destination phone number or JID.
 */
class GowaChannel
{
    public function __construct(
        private readonly GowaClient $client,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toGowa')) {
            return;
        }

        $to = method_exists($notifiable, 'routeNotificationForGowa')
            ? $notifiable->routeNotificationForGowa($notification)
            : null;

        if (empty($to)) {
            return;
        }

        /** @var GowaMessage $message */
        $message = $notification->toGowa($notifiable);

        $message->send($this->client, $to);
    }
}
