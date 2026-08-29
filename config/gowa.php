<?php

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
    | Webhook
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256 secret the GOWA server uses to sign webhook deliveries.
    | Set `path` to null/empty to skip route registration.
    |
    */
    'webhook' => [
        'secret' => env('GOWA_WEBHOOK_SECRET'),
        'path'   => env('GOWA_WEBHOOK_PATH', 'webhooks/gowa'),
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'table_names' => [
        'instances'     => 'gowa_instances',
        'conversations' => 'gowa_conversations',
        'messages'      => 'gowa_messages',
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
