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

namespace Lemric\BatchProcessing\Explorer;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, StepExecution};

/**
 * Read-only query API for batch metadata.
 */
interface JobExplorerInterface
{
    /**
     * Paginated search for JobInstances by job name.
     *
     * @return list<JobInstance>
     */
    public function findJobInstancesByJobName(string $jobName, int $start, int $count): array;

    /**
     * @return list<JobExecution>
     */
    public function findRunningJobExecutions(string $jobName): array;

    public function getJobExecution(int $executionId): ?JobExecution;

    /**
     * Returns the total number of JobExecutions for the given job name.
     */
    public function getJobExecutionCount(string $jobName): int;

    /**
     * @return list<JobExecution>
     */
    public function getJobExecutions(JobInstance $instance): array;

    public function getJobInstance(int $instanceId): ?JobInstance;

    /**
     * Returns the total number of JobInstances for the given job name.
     */
    public function getJobInstanceCount(string $jobName): int;

    /**
     * @return list<JobInstance>
     */
    public function getJobInstances(string $jobName, int $start = 0, int $count = 20): array;

    /**
     * @return list<string>
     */
    public function getJobNames(): array;

    public function getStepExecution(int $jobExecutionId, int $stepExecutionId): ?StepExecution;
}
