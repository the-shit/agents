<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Container Control Daemon Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Container Control Daemon that manages agent
    | containers. The daemon provides a REST API for spawning, listing,
    | killing, and monitoring agent containers.
    |
    */

    'daemon' => [
        'url' => env('CONTAINER_DAEMON_URL', 'http://localhost:9092'),
        'token' => env('CONTAINER_DAEMON_TOKEN', ''),
        'timeout' => (int) env('CONTAINER_DAEMON_TIMEOUT', 30),
    ],
];
