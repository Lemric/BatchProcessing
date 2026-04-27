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

namespace Lemric\BatchProcessing\Repository\Dao;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters};

/**
 * DAO contract for {@see JobExecution} persistence.
 */
interface JobExecutionDaoInterface
{
    public function createJobExecution(JobInstance $instance, JobParameters $parameters): JobExecution;

    public function deleteJobExecution(int $executionId): void;

    /**
     * @return list<JobExecution>
     */
    public function findJobExecutions(JobInstance $instance): array;

    /**
     * @return list<JobExecution>
     */
    public function findRunningJobExecutions(string $jobName): array;

    public function getJobExecution(int $executionId): ?JobExecution;

    public function getLastJobExecution(JobInstance $instance): ?JobExecution;

    public function update(JobExecution $jobExecution): void;
}
