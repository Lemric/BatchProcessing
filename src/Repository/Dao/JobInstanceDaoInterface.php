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

use Lemric\BatchProcessing\Domain\{JobInstance, JobParameters};

/**
 * DAO contract for {@see JobInstance} persistence.
 */
interface JobInstanceDaoInterface
{
    public function createJobInstance(string $jobName, JobParameters $parameters): JobInstance;

    public function deleteJobInstance(int $instanceId): void;

    /**
     * @return list<JobInstance>
     */
    public function findJobInstancesByName(string $jobName, int $start = 0, int $count = 20): array;

    public function getJobInstance(int $instanceId): ?JobInstance;

    public function getJobInstanceByJobNameAndParameters(string $jobName, JobParameters $parameters): ?JobInstance;

    /**
     * @return list<string>
     */
    public function getJobNames(): array;

    public function getLastJobInstance(string $jobName): ?JobInstance;
}
