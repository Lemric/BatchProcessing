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

namespace Lemric\BatchProcessing\Bridge\Laravel\Queue;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Security\{AsyncJobMessageSigner, AsyncJobMessageSigningRequirement};

/**
 * Callable adapter that hands off asynchronous job execution to Laravel's queue. Used by
 * {@see \Lemric\BatchProcessing\Launcher\AsyncJobLauncher} as its `$dispatcher` callable.
 */
final readonly class QueueJobDispatcher
{
    public function __construct(
        private QueueFactory $queue,
        private ?string $connection = null,
        private ?string $queueName = null,
        private string $messageSecret = '',
    ) {
    }

    public function __invoke(int $jobExecutionId, string $jobName, JobParameters $parameters): void
    {
        AsyncJobMessageSigningRequirement::assertSecretForDispatch($this->messageSecret, 'Laravel QueueJobDispatcher');
        $issuedAt = time();
        $parametersJobKey = $parameters->toJobKey();
        $signature = AsyncJobMessageSigner::sign($this->messageSecret, $jobExecutionId, $jobName, $issuedAt, $parametersJobKey);
        $job = new RunJobQueueJob($jobExecutionId, $jobName, $issuedAt, $signature, $parametersJobKey);
        if (null !== $this->queueName) {
            $job->onQueue($this->queueName);
        }
        if (null !== $this->connection) {
            $job->onConnection($this->connection);
        }
        $this->queue->connection($this->connection)->pushOn(
            $this->queueName ?? 'default',
            $job,
        );
    }
}
