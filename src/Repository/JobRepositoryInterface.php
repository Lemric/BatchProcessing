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

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters, StepExecution};

/**
 * Persistence port for batch metadata. Two reference implementations are bundled:
 *  - {@see InMemoryJobRepository} for tests and in-memory pipelines.
 *  - {@see PdoJobRepository} for production usage on top of MySQL/PostgreSQL/SQLite.
 */
interface JobRepositoryInterface
{
    // ── Step Execution ────────────────────────────────────────────────────

    public function add(StepExecution $stepExecution): void;

    // ── Job Execution ─────────────────────────────────────────────────────

    public function createJobExecution(JobInstance $instance, JobParameters $parameters): JobExecution;
    // ── Job Instance ──────────────────────────────────────────────────────

    public function createJobInstance(string $jobName, JobParameters $parameters): JobInstance;

    /**
     * Deletes a JobExecution and its associated step executions, contexts and parameters.
     */
    public function deleteJobExecution(int $executionId): void;

    /**
     * Deletes a JobInstance and all associated executions.
     */
    public function deleteJobInstance(int $instanceId): void;

    /**
     * @return list<JobExecution>
     */
    public function findJobExecutions(JobInstance $instance): array;

    /**
     * @return list<JobInstance>
     */
    public function findJobInstancesByName(string $jobName, int $start = 0, int $count = 20): array;

    /**
     * @return list<JobExecution>
     */
    public function findRunningJobExecutions(string $jobName): array;

    public function getJobExecution(int $executionId): ?JobExecution;

    public function getJobInstance(int $instanceId): ?JobInstance;

    public function getJobInstanceByJobNameAndParameters(string $jobName, JobParameters $parameters): ?JobInstance;

    /**
     * Returns distinct job names that have been launched.
     *
     * @return list<string>
     */
    public function getJobNames(): array;

    public function getLastJobExecution(JobInstance $instance): ?JobExecution;

    public function getLastJobInstance(string $jobName): ?JobInstance;

    public function getLastStepExecution(JobInstance $instance, string $stepName): ?StepExecution;

    public function getStepExecutionCount(JobInstance $instance, string $stepName): int;

    public function isJobInstanceExists(string $jobName, JobParameters $parameters): bool;

    public function update(StepExecution $stepExecution): void;

    public function updateExecutionContext(StepExecution $stepExecution): void;

    public function updateJobExecution(JobExecution $jobExecution): void;

    public function updateJobExecutionContext(JobExecution $jobExecution): void;
}
