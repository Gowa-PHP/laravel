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
 * Your notifiable must implement `routeNotificationForGowa()` or `routeNotificationFor('gowa')`:
 *
 * ```php
 * public function routeNotificationForGowa(): array|string
 * {
 *     return ['device' => $this->gowa_device_id, 'to' => $this->phone];
 *     // or return $this->phone;
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

        /** @var array{device?: string, to?: string}|string|null $route */
        $route = method_exists($notifiable, 'routeNotificationForGowa')
            ? $notifiable->routeNotificationForGowa($notification)
            : ($notifiable->routeNotificationFor('gowa', $notification) ?? null);

        if (is_string($route)) {
            $route = ['to' => $route];
        }

        if (empty($route['to'])) {
            return;
        }

        if (empty($route['device'])) {
            $instanceModel = config('gowa.models.instance', \Gowa\Laravel\Models\GowaInstance::class);
            $defaultInstance = $instanceModel::query()->first();
            $route['device'] = config('gowa.default_device_id') ?: ($defaultInstance?->device_id ?? '');
        }

        if (empty($route['device'])) {
            return;
        }

        /** @var GowaMessage $message */
        $message = $notification->toGowa($notifiable);

        $message->send($this->client, [
            'device' => (string) $route['device'],
            'to'     => (string) $route['to'],
        ]);
    }
}
