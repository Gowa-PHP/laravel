<?php

declare(strict_types=1);

$app = require __DIR__ . '/bootstrap.php';

use Gowa\Laravel\Notifications\GowaChannel;
use Gowa\Laravel\Notifications\GowaMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class OrderStatusNotification extends Notification
{
    public function __construct(public string $orderId, public string $status) {}

    public function via(mixed $notifiable): array
    {
        return [GowaChannel::class];
    }

    public function toGowa(mixed $notifiable): GowaMessage
    {
        return GowaMessage::create("📦 Order #{$this->orderId} status update: {$this->status}");
    }
}

$recipient = getenv('GOWA_RECIPIENT') ?: '5511999998888';

echo "Demonstrating Laravel Notification Channel sending...\n";
echo "Recipient: {$recipient}\n";

if (getenv('GOWA_SEND_MESSAGE') !== '1') {
    echo "Dry run mode (GOWA_SEND_MESSAGE != 1). Notification object built:\n";
    $notification = new OrderStatusNotification('ORD-12345', 'Shipped');
    $gowaMessage = $notification->toGowa(null);
    echo "✔ GowaMessage instance constructed successfully!\n";
    exit(0);
}

try {
    NotificationFacade::route('gowa', $recipient)
        ->notify(new OrderStatusNotification('ORD-12345', 'Shipped'));

    echo "✔ Notification sent successfully via GowaChannel!\n";
    exit(0);
} catch (Throwable $e) {
    echo "❌ Failed to send notification: " . $e->getMessage() . "\n";
    exit(1);
}
