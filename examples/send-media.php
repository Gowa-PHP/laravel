<?php

declare(strict_types=1);

$app = require __DIR__ . '/bootstrap.php';

use Gowa\Laravel\Facades\Gowa;
use Illuminate\Support\Facades\Storage;

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);

    return $value === false ? '' : trim($value);
}

$recipient = getenv('GOWA_RECIPIENT') ?: prompt('Recipient number (international format, e.g. 5511999998888): ');

if ($recipient === '') {
    fwrite(STDERR, "Recipient number is required.\n");
    exit(1);
}

echo "\nDemonstrating Fluent Media & Laravel Storage Sending...\n";
echo "1. Image via external URL\n";
echo "2. Document via Laravel Storage (disk)\n";
echo "3. Geolocation & Interactive Poll\n\n";

if (getenv('GOWA_SEND_MESSAGE') !== '1') {
    $confirmation = prompt('This sends real WhatsApp messages. Type SEND to continue: ');
    if ($confirmation !== 'SEND') {
        fwrite(STDERR, "Execution cancelled (dry-run mode).\n");
        exit(0);
    }
}

try {
    // 1. Send image via URL
    echo "Sending image via URL...\n";
    $responseImage = Gowa::to($recipient)
        ->image('https://raw.githubusercontent.com/Gowa-PHP/laravel/main/art/banner.png', 'Check out the new gowa-laravel!')
        ->send();
    echo "✔ Image sent! Provider ID: {$responseImage->providerMessageId}\n\n";

    // 2. Send document from Laravel Storage Disk
    Storage::disk('local')->put('samples/invoice.txt', "INVOICE #2026-001\nTotal: $100.00\nStatus: Paid");
    echo "Sending document from Storage disk ('local')...\n";
    $responseDoc = Gowa::to($recipient)
        ->disk('local')
        ->document('samples/invoice.txt', filename: 'Fatura_2026.txt', caption: 'Your invoice')
        ->send();
    echo "✔ Document sent! Provider ID: {$responseDoc->providerMessageId}\n\n";

    // 3. Send Location & Poll
    echo "Sending interactive Poll...\n";
    $responsePoll = Gowa::to($recipient)
        ->poll('What feature do you like most in gowa-laravel?', [
            'Fluent API',
            'Laravel Storage Integration',
            'Webhook Handling',
            'Notification Channel',
        ], maxSelections: 1)
        ->send();
    echo "✔ Poll sent! Provider ID: {$responsePoll->providerMessageId}\n\n";

    echo "✔ All fluent media examples executed successfully!\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "❌ Failed to send media: {$e->getMessage()}\n");
    exit(1);
}
