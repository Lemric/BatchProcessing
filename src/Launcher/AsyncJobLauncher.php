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

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};
use Lemric\BatchProcessing\Job\JobInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Asynchronous launcher that delegates actual job execution to a user-supplied dispatcher.
 * The job is NOT executed inline — the returned {@see JobExecution} will be in STARTING status.
 */
final class AsyncJobLauncher extends AbstractJobLauncher
{
    /**
     * @param callable(int, string, JobParameters): void $dispatcher receives (jobExecutionId, jobName, parameters)
     */
    public function __construct(
        JobRepositoryInterface $jobRepository,
        private $dispatcher,
    ) {
        parent::__construct($jobRepository);
    }

    public function run(JobInterface $job, JobParameters $parameters): JobExecution
    {
        $execution = $this->createValidatedExecution($job, $parameters);

        $this->logger->info('Dispatching job asynchronously', [
            'job' => $job->getName(),
            'jobExecutionId' => $execution->getId(),
        ]);

        ($this->dispatcher)($execution->getId() ?? 0, $job->getName(), $parameters);

        return $execution;
    }
}
