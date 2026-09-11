<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | GOWA Server
    |--------------------------------------------------------------------------
    |
    | Base URL of your go-whatsapp-web-multidevice server.
    | Leave empty to disable the GOWA driver.
    |
    */
    'base_url' => env('GOWA_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Basic auth — one credential covers all paired devices.
    |
    */
    'username' => env('GOWA_USERNAME'),
    'password' => env('GOWA_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP timeout in seconds. Keep short: stalled server should not
    | hold up workers indefinitely.
    |
    */
    'timeout' => (int) env('GOWA_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Stateless Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, GOWA operates purely as an API client and event dispatcher:
    | - No package migrations are loaded.
    | - No package database queries or Eloquent models are executed.
    | - Auto-sync to package tables is disabled.
    | - Webhooks are validated via the global HMAC secret and dispatched
    |   as standard Laravel events for your application models to handle.
    |
    */
    'stateless'   => (bool) env('GOWA_STATELESS', env('GOWA_DRIVER_ONLY', false)),
    'driver_only' => (bool) env('GOWA_STATELESS', env('GOWA_DRIVER_ONLY', false)),

    /*
    |--------------------------------------------------------------------------
    | Package Migrations
    |--------------------------------------------------------------------------
    |
    | Set to false if you are using custom tables and do not want the package
    | default migrations to be automatically registered.
    | Automatically disabled when `stateless` is true.
    |
    */
    'migrations' => (bool) env('GOWA_MIGRATIONS', ! (bool) env('GOWA_STATELESS', env('GOWA_DRIVER_ONLY', false))),

    /*
    |--------------------------------------------------------------------------
    | Default Device ID
    |--------------------------------------------------------------------------
    |
    | Default GOWA device ID to use when sending messages if not explicitly
    | specified via `from($deviceId)` or in notification routing.
    | When omitted, the first connected instance in database is used.
    |
    */
    'default_device_id' => env('GOWA_DEFAULT_DEVICE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Database Auto-Sync
    |--------------------------------------------------------------------------
    |
    | Automatically persist conversations and messages in the database.
    | `inbound`: record incoming messages and acks from webhooks.
    | `outbound`: record messages sent via Gowa Facade or Notification channel.
    | Automatically disabled when `stateless` is true.
    |
    */
    'auto_sync' => [
        'inbound'  => (bool) env('GOWA_AUTO_SYNC_INBOUND', env('GOWA_WEBHOOK_AUTO_SYNC', ! (bool) env('GOWA_STATELESS', env('GOWA_DRIVER_ONLY', false)))),
        'outbound' => (bool) env('GOWA_AUTO_SYNC_OUTBOUND', ! (bool) env('GOWA_STATELESS', env('GOWA_DRIVER_ONLY', false))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256 secret the GOWA server uses to sign webhook deliveries.
    | Set `path` to null/empty to skip route registration.
    | `auto_sync`: automatically persist inbound/outbound messages and acks in DB.
    | `record_calls`: record all webhook deliveries in `gowa_webhook_calls` table.
    | `log_requests`: log raw incoming webhook payloads to Laravel log.
    | `prune_after_days`: days before old webhook call logs are pruned.
    |
    */
    'webhook' => [
        'secret'           => env('GOWA_WEBHOOK_SECRET'),
        'path'             => env('GOWA_WEBHOOK_PATH', 'webhooks/gowa'),
        'auto_sync'        => (bool) env('GOWA_WEBHOOK_AUTO_SYNC', ! (bool) env('GOWA_STATELESS', env('GOWA_DRIVER_ONLY', false))),
        'record_calls'     => (bool) env('GOWA_WEBHOOK_RECORD_CALLS', ! (bool) env('GOWA_STATELESS', env('GOWA_DRIVER_ONLY', false))),
        'log_requests'     => (bool) env('GOWA_LOG_WEBHOOKS', false),
        'log_channel'      => env('GOWA_LOG_CHANNEL'),
        'prune_after_days' => (int) env('GOWA_WEBHOOK_PRUNE_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Override default Eloquent models. Extend the base model and point here.
    |
    */
    'models' => [
        'instance'     => \Gowa\Laravel\Models\GowaInstance::class,
        'conversation' => \Gowa\Laravel\Models\GowaConversation::class,
        'message'      => \Gowa\Laravel\Models\GowaMessage::class,
        'webhook_call' => \Gowa\Laravel\Models\GowaWebhookCall::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    */
    'table_names' => [
        'instances'     => 'gowa_instances',
        'conversations' => 'gowa_conversations',
        'messages'      => 'gowa_messages',
        'webhook_calls' => 'gowa_webhook_calls',
    ],

    /*
    |--------------------------------------------------------------------------
    | Teams
    |--------------------------------------------------------------------------
    |
    | When enabled, migrations include a `team_id` foreign key and models
    | scope queries to the current team.
    |
    */
    'teams' => [
        'enabled'     => (bool) env('GOWA_TEAMS', false),
        'foreign_key' => env('GOWA_TEAM_FOREIGN_KEY', 'team_id'),
    ],

];
