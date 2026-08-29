<?php

declare(strict_types=1);

namespace Gowa\Laravel\Notifications;

use Gowa\Sdk\GowaClient;
use Illuminate\Notifications\Notification;

/**
 * Laravel Notification Channel for GOWA WhatsApp.
 *
 * Your Notification must implement `toGowa(mixed $notifiable): GowaMessage`.
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

        /** @var array{device: string, to: string}|null $route */
        $route = method_exists($notifiable, 'routeNotificationForGowa')
            ? $notifiable->routeNotificationForGowa($notification)
            : null;

        if (empty($route['device']) || empty($route['to'])) {
            return;
        }

        /** @var GowaMessage $message */
        $message = $notification->toGowa($notifiable);

        $message->send($this->client, $route);
    }
}
