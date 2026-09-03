<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Gowa\Laravel\GowaServiceProvider;
use Orchestra\Testbench\Foundation\Application;

// Load .env if present
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Bootstrap Laravel Application via Orchestra Testbench
$basePath = __DIR__ . '/../vendor/orchestra/testbench-core/laravel';
$app = (new Application($basePath))->createApplication();

// Register Package Provider
$app->register(GowaServiceProvider::class);

// Configure Gowa package from environment
config([
    'gowa.base_url'       => getenv('GOWA_BASE_URL') ?: 'https://gowa.example.com',
    'gowa.username'       => getenv('GOWA_USERNAME') ?: 'admin',
    'gowa.password'       => getenv('GOWA_PASSWORD') ?: 'secret',
    'gowa.timeout'        => (int) (getenv('GOWA_TIMEOUT') ?: 15),
    'gowa.webhook_secret' => getenv('GOWA_WEBHOOK_SECRET') ?: null,
    'gowa.webhook_path'   => getenv('GOWA_WEBHOOK_PATH') ?: 'webhooks/gowa',
]);

// Configure Database Connection (SQLite in-memory for standalone example scripts)
config([
    'database.default'             => 'testing',
    'database.connections.testing' => [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ],
]);

// Boot application
$app->boot();

// Load package database migrations
$migrator = $app->make('migrator');
if (! $migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}
$migrator->run([__DIR__ . '/../database/migrations']);

return $app;
