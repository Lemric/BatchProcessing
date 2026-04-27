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

namespace Lemric\BatchProcessing\Repository\Dao\Pdo;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters};
use Lemric\BatchProcessing\Repository\Dao\JobExecutionDaoInterface;
use Lemric\BatchProcessing\Repository\PdoJobRepository;

final readonly class PdoJobExecutionDao implements JobExecutionDaoInterface
{
    public function __construct(private PdoJobRepository $repository)
    {
    }

    public function createJobExecution(JobInstance $instance, JobParameters $parameters): JobExecution
    {
        return $this->repository->createJobExecution($instance, $parameters);
    }

    public function deleteJobExecution(int $executionId): void
    {
        $this->repository->deleteJobExecution($executionId);
    }

    public function findJobExecutions(JobInstance $instance): array
    {
        return $this->repository->findJobExecutions($instance);
    }

    public function findRunningJobExecutions(string $jobName): array
    {
        return $this->repository->findRunningJobExecutions($jobName);
    }

    public function getJobExecution(int $executionId): ?JobExecution
    {
        return $this->repository->getJobExecution($executionId);
    }

    public function getLastJobExecution(JobInstance $instance): ?JobExecution
    {
        return $this->repository->getLastJobExecution($instance);
    }

    public function update(JobExecution $jobExecution): void
    {
        $this->repository->updateJobExecution($jobExecution);
    }
}
