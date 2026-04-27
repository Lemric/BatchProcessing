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

namespace Lemric\BatchProcessing\Operator;

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};

/**
 * Administrative operations on jobs and executions.
 */
interface JobOperatorInterface
{
    /**
     * Marks an unrecoverable execution as ABANDONED so that a new execution can be created
     * for the same {@see JobInstance}.
     */
    public function abandon(int $executionId): JobExecution;

    /**
     * @return list<int> execution IDs for the given instance
     */
    public function getExecutions(int $instanceId): array;

    /**
     * Returns the number of job executions for the given job name.
     */
    public function getJobExecutionCount(string $jobName): int;

    /**
     * Returns the number of job instances for the given job name.
     */
    public function getJobInstanceCount(string $jobName): int;

    /**
     * @return list<int> instance IDs for the given job
     */
    public function getJobInstances(string $jobName, int $start, int $count): array;

    /**
     * @return list<string> all registered job names
     */
    public function getJobNames(): array;

    /**
     * Returns the parameters of the given execution as a string.
     */
    public function getParameters(int $executionId): string;

    /**
     * @return list<int> execution IDs of currently running executions
     */
    public function getRunningExecutions(string $jobName): array;

    /**
     * @return array<int, string> step execution ID → summary string
     */
    public function getStepExecutionSummaries(int $executionId): array;

    /**
     * Returns a summary for a single step execution.
     */
    public function getStepExecutionSummary(int $jobExecutionId, int $stepExecutionId): string;

    /**
     * Returns a summary string for the given execution.
     */
    public function getSummary(int $executionId): string;

    /**
     * Restarts the supplied (failed/stopped) execution by id, returning the new execution id.
     */
    public function restart(int $executionId): int;

    /**
     * Starts a job, returning the new execution id.
     */
    public function start(string $jobName, JobParameters $parameters): int;

    /**
     * Starts the next {@see \Lemric\BatchProcessing\Domain\JobInstance} of the job, deriving
     * its identifying parameters from the previously launched instance using the job's
     * incrementer.
     */
    public function startNextInstance(string $jobName): int;

    /**
     * Requests a graceful stop of the running execution.
     */
    public function stop(int $executionId): bool;
}
