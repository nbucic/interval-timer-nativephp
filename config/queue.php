<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel 13 "background" driver: serialises each job closure into a
    | base64 env-var and spawns a dedicated `artisan invoke-serialized-closure`
    | process.  No database, no Redis, no persistent storage — purely in-process
    | / temporary.  Perfect for NativePHP where neither Redis nor SQLite are
    | available on-device.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'background'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'background' => [
            'driver'       => 'background',
            'after_commit' => false,
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | The background driver does not persist failed jobs.  Set to null so
    | Laravel does not try to write to a missing `failed_jobs` table.
    |
    */

    'failed' => [
        'driver'   => env('QUEUE_FAILED_DRIVER', 'null'),
        'database' => null,
        'table'    => null,
    ],

];
