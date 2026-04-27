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

use Illuminate\Contracts\Queue\Job;
use Lemric\BatchProcessing\Domain\BatchStatus;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Psr\Log\{LoggerAwareInterface, LoggerInterface, NullLogger};
use Throwable;

/**
 * Laravel queue middleware that updates batch job metadata on retry/failure at the queue level.
 *
 * When a queue job exhausts its retry budget, this middleware marks the corresponding
 * {@see \Lemric\BatchProcessing\Domain\JobExecution} as FAILED so that the batch framework
 * correctly reflects the outcome.
 *
 * Usage:
 *   class RunJobQueueJob implements ShouldQueue {
 *       public function middleware(): array {
 *           return [new RunStepJobMiddleware()];
 *       }
 *   }
 */
final class RunStepJobMiddleware implements LoggerAwareInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ?JobRepositoryInterface $repository = null,
    ) {
        $this->logger = new NullLogger();
    }

    /**
     * @param object $job The queue job (RunJobQueueJob)
     */
    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (Throwable $e) {
            // If this is the final attempt, mark the batch execution as FAILED.
            if ($job instanceof RunJobQueueJob && null !== $this->repository) {
                $execution = $this->repository->getJobExecution($job->jobExecutionId);
                if (null !== $execution && $execution->isRunning()) {
                    $execution->setStatus(BatchStatus::FAILED);
                    $execution->addFailureException($e);
                    $this->repository->updateJobExecution($execution);
                    $this->logger->error('Batch job failed on queue', [
                        'jobName' => $job->jobName,
                        'executionId' => $job->jobExecutionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
