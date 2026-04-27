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

namespace Lemric\BatchProcessing\Repository;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters};
use Lemric\BatchProcessing\Exception\{JobExecutionAlreadyRunningException, JobInstanceAlreadyCompleteException};

/**
 * Template Method base class for {@see JobRepositoryInterface} implementations.
 * Encapsulates common guard logic (duplicate detection, running-check, restart validation)
 * shared between {@see InMemoryJobRepository} and {@see PdoJobRepository}.
 */
abstract class AbstractJobRepository implements JobRepositoryInterface
{
    public function getLastJobExecution(JobInstance $instance): ?JobExecution
    {
        $list = $this->findJobExecutions($instance);

        return $list[0] ?? null;
    }

    public function getLastJobInstance(string $jobName): ?JobInstance
    {
        $list = $this->findJobInstancesByName($jobName, 0, 1);

        return $list[0] ?? null;
    }

    /**
     * Creates a job key string from the given parameters, suitable for uniqueness checks.
     */
    protected function buildJobKey(JobParameters $parameters): string
    {
        return $parameters->toJobKey();
    }

    /**
     * Validates that a new JobExecution is allowed for the given instance/parameters combination.
     *
     * @throws JobExecutionAlreadyRunningException if an execution is currently running
     * @throws JobInstanceAlreadyCompleteException if the last execution already completed successfully
     */
    protected function validateNewExecution(JobInstance $instance): void
    {
        $last = $this->getLastJobExecution($instance);
        if (null === $last) {
            return;
        }
        if ($last->isRunning()) {
            throw new JobExecutionAlreadyRunningException(sprintf('A job execution for instance "%s" (id=%d) is already running.', $instance->getJobName(), $instance->getId() ?? 0));
        }
    }
}
