<?php

declare(strict_types=1);

$app = require __DIR__ . '/bootstrap.php';

use Gowa\Laravel\Facades\Gowa;
use Gowa\Laravel\Models\GowaInstance;

$deviceId = getenv('GOWA_DEVICE_ID') ?: 'my-instance-uuid';

echo "1. Checking configured GOWA server connection...\n";
echo '   Base URL: ' . config('gowa.base_url') . "\n";
echo '   Device ID: ' . $deviceId . "\n\n";

try {
    // Fetch device status using Gowa Facade
    $deviceInfo = Gowa::device($deviceId);
    echo "✔ Device found on GOWA server!\n";
    echo '   Device JID: ' . ($deviceInfo->jid ?? 'N/A') . "\n";
    echo '   State: ' . ($deviceInfo->state ?? 'unknown') . "\n\n";

    // Persist / sync in Laravel Eloquent GowaInstance model
    $instance = GowaInstance::updateOrCreate(
        ['device_id' => $deviceId],
        [
            'name'           => 'Test Instance',
            'status'         => $deviceInfo->state ?? 'open',
            'webhook_secret' => config('gowa.webhook_secret'),
        ],
    );

    echo "✔ Synced Eloquent GowaInstance Model:\n";
    echo '   ID: ' . $instance->id . "\n";
    echo '   Status Connected: ' . ($instance->status->isConnected() ? 'YES' : 'NO') . "\n";

    exit(0);
} catch (Throwable $e) {
    echo '❌ Unable to connect or find device: ' . $e->getMessage() . "\n";
    exit(1);
}
