<?php

declare(strict_types=1);

$app = require __DIR__ . '/bootstrap.php';

use Gowa\Laravel\Facades\Gowa;

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);

    return $value === false ? '' : trim($value);
}

$recipient = getenv('GOWA_RECIPIENT') ?: prompt('Recipient number (international format, e.g. 5511999998888): ');
$text = getenv('GOWA_TEST_MESSAGE') ?: prompt('Message text: ');

if ($recipient === '' || $text === '') {
    fwrite(STDERR, "Recipient number and message text are required.\n");
    exit(1);
}

if (getenv('GOWA_SEND_MESSAGE') !== '1') {
    $confirmation = prompt("This sends a real WhatsApp message. Type SEND to continue: ");
    if ($confirmation !== 'SEND') {
        fwrite(STDERR, "Message sending cancelled.\n");
        exit(1);
    }
}

try {
    $response = Gowa::sendText($recipient, $text);
    echo "\n✔ Message sent successfully!\n";
    echo "   Response Details:\n";
    var_dump($response);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "❌ Unable to send test message: {$exception->getMessage()}\n");
    exit(1);
}
