<?php

declare(strict_types=1);

namespace Gowa\Laravel\Tests;

use Gowa\Laravel\GowaServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [GowaServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', $driver);

        $app['config']->set('database.connections.sqlite', [
            'driver'                  => 'sqlite',
            'database'                => env('DB_DATABASE', ':memory:'),
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('database.connections.mysql', [
            'driver'         => 'mysql',
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '3336'),
            'database'       => env('DB_DATABASE', 'gowa_test'),
            'username'       => env('DB_USERNAME', 'gowa'),
            'password'       => env('DB_PASSWORD', 'gowa_secret'),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'strict'         => true,
            'engine'         => null,
        ]);

        $app['config']->set('database.connections.pgsql', [
            'driver'   => 'pgsql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', '5436'),
            'database' => env('DB_DATABASE', 'gowa_test'),
            'username' => env('DB_USERNAME', 'gowa'),
            'password' => env('DB_PASSWORD', 'gowa_secret'),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
            'sslmode'  => 'prefer',
        ]);
    }

    protected function tearDown(): void
    {
        \Gowa\Laravel\Facades\Gowa::$runsMigrations = true;

        parent::tearDown();
    }
}
