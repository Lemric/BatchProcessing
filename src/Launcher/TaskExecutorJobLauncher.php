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

namespace Lemric\BatchProcessing\Launcher;

use Lemric\BatchProcessing\Core\{SyncTaskExecutor, TaskExecutorInterface};
use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};
use Lemric\BatchProcessing\Job\JobInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Throwable;

/**
 * Job launcher parameterised by a {@see TaskExecutorInterface}: synchronous by default,
 * but can be switched to async execution (Fiber, process) by injecting a different executor.
 */
final class TaskExecutorJobLauncher extends AbstractJobLauncher
{
    public function __construct(
        JobRepositoryInterface $jobRepository,
        private readonly TaskExecutorInterface $taskExecutor = new SyncTaskExecutor(),
    ) {
        parent::__construct($jobRepository);
    }

    public function run(JobInterface $job, JobParameters $parameters): JobExecution
    {
        $execution = $this->createValidatedExecution($job, $parameters);

        $this->logger->info('Launching job via TaskExecutor', [
            'job' => $job->getName(),
            'jobExecutionId' => $execution->getId(),
        ]);

        $this->taskExecutor->execute(function () use ($job, $execution): void {
            try {
                $job->execute($execution);
            } catch (Throwable $e) {
                $this->logger->error('Job execution raised', [
                    'job' => $job->getName(),
                    'exception' => $e,
                ]);
            }
        });

        return $execution;
    }
}
