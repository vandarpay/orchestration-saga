<?php

use Illuminate\Support\Str;

return [
    'rabbitmq' => [
        'host' => env('RPC_RABBITMQ_HOST', 'localhost'),
        'port' => env('RPC_RABBITMQ_PORT', 5672),
        'user' => env('RPC_RABBITMQ_USER', 'guest'),
        'password' => env('RPC_RABBITMQ_PASSWORD', 'guest'),
        'connection_timeout' => env('RPC_RABBITMQ_CONNECTION_TIMEOUT', 10),
        'read_write_timeout' => env('RPC_RABBITMQ_READ_WRITE_TIMEOUT', 10),
        'keep_alive' => env('RPC_RABBITMQ_KEEP_ALIVE', true),
        'use_exchange_log' => env('RPC_USE_EXCHANGE_LOG', true),
        'request_queue' => Str::slug(config('app.name'), '_') . '_request',
        'response_queue' => Str::slug(config('app.name'), '_') . '_response',
    ],
    'request_timeout' => env('RPC_REQUEST_TIMEOUT', 30),
    'debug' => env('RPC_DEBUG', false),
    'debug_log_channel' => env('RPC_DEBUG_LOG_CHANNEL'),
    'log_job' => env('LOG_JOB', '')
];
