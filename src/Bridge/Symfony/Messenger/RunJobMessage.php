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

namespace Lemric\BatchProcessing\Bridge\Symfony\Messenger;

/**
 * Symfony Messenger envelope for asynchronous job execution. Carries everything the worker
 * needs to resume a job execution previously scheduled by {@see MessengerJobDispatcher}.
 */
final readonly class RunJobMessage
{
    public function __construct(
        public int $jobExecutionId,
        public string $jobName,
        public int $messageIssuedAt,
        public ?string $signature = null,
        public ?string $parametersJobKey = null,
    ) {
    }
}
