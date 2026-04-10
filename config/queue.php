<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | The Android runtime hardcodes QUEUE_CONNECTION=database, so the database
    | driver is always used on-device. The jobs table is created via migration.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
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
