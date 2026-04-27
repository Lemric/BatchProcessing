<?php

/**
 * This file is part of the Lemric package.
 * (c) Lemric
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
declare(strict_types=1);

/*
 * Default configuration for Lemric BatchProcessing — published via:
 *   php artisan vendor:publish --provider="Lemric\BatchProcessing\Bridge\Laravel\BatchProcessingServiceProvider"
 *
 * Security (see SECURITY.md in the package root):
 * - When async.enabled is true, async.message_secret MUST be set (strong random value, e.g. 32+ bytes base64).
 *   Queue workers use the same secret to verify RunJobQueueJob payloads (HMAC-SHA256 over execution id, job name, issue time).
 * - async.message_ttl_seconds bounds replay of signed queue messages (default 7 days).
 * - Job parameters and execution contexts are persisted as text/JSON. Minimize PII/secrets in parameters;
 *   use DB encryption at rest, least-privilege DB users, and retention policies where regulations apply.
 * - Restrict who can run batch:job:* Artisan commands and who can publish to the batch queue connection.
 */
$asyncMessageTtlEnv = env('BATCH_ASYNC_MESSAGE_TTL', '604800');
$asyncMessageTtlStr = is_scalar($asyncMessageTtlEnv) ? (string) $asyncMessageTtlEnv : '604800';

return [
    'table_prefix' => 'batch_',
    'connection' => env('BATCH_DB_CONNECTION', 'mysql'),

    'default_retry' => [
        'max_attempts' => 3,
        'retryable_exceptions' => [RuntimeException::class],
        'backoff' => [
            'type' => 'exponential',
            'initial' => 200,
            'max' => 10000,
            'multiplier' => 2.0,
        ],
    ],

    'default_skip' => [
        'skip_limit' => 0,
        'skippable_exceptions' => [],
    ],

    'async' => [
        'enabled' => env('BATCH_ASYNC', false),
        'connection' => env('BATCH_QUEUE_CONNECTION'),
        'queue' => env('BATCH_QUEUE', 'batch'),
        'message_secret' => env('BATCH_ASYNC_MESSAGE_SECRET'),
        'message_ttl_seconds' => max(60, (int) (filter_var($asyncMessageTtlStr, \FILTER_VALIDATE_INT) ?: 604800)),
    ],
];
