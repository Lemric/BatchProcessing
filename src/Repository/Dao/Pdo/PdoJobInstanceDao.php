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

use Lemric\BatchProcessing\Domain\{JobInstance, JobParameters};
use Lemric\BatchProcessing\Repository\Dao\JobInstanceDaoInterface;
use Lemric\BatchProcessing\Repository\PdoJobRepository;

/**
 * Thin DAO facade over {@see PdoJobRepository}.
 */
final readonly class PdoJobInstanceDao implements JobInstanceDaoInterface
{
    public function __construct(private PdoJobRepository $repository)
    {
    }

    public function createJobInstance(string $jobName, JobParameters $parameters): JobInstance
    {
        return $this->repository->createJobInstance($jobName, $parameters);
    }

    public function deleteJobInstance(int $instanceId): void
    {
        $this->repository->deleteJobInstance($instanceId);
    }

    public function findJobInstancesByName(string $jobName, int $start = 0, int $count = 20): array
    {
        return $this->repository->findJobInstancesByName($jobName, $start, $count);
    }

    public function getJobInstance(int $instanceId): ?JobInstance
    {
        return $this->repository->getJobInstance($instanceId);
    }

    public function getJobInstanceByJobNameAndParameters(string $jobName, JobParameters $parameters): ?JobInstance
    {
        return $this->repository->getJobInstanceByJobNameAndParameters($jobName, $parameters);
    }

    public function getJobNames(): array
    {
        return $this->repository->getJobNames();
    }

    public function getLastJobInstance(string $jobName): ?JobInstance
    {
        return $this->repository->getLastJobInstance($jobName);
    }
}
