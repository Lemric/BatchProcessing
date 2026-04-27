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

namespace Lemric\BatchProcessing\Testing;

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Utilities for cleaning up and inserting test metadata into a job repository.
 */
final class JobRepositoryTestUtils
{
    public function __construct(
        private readonly JobRepositoryInterface $jobRepository,
    ) {
    }

    /**
     * Create a simple test JobExecution with the given name.
     */
    public function createJobExecution(string $jobName, ?JobParameters $params = null): JobExecution
    {
        $parameters = $params ?? JobParameters::empty();
        $instance = $this->jobRepository->createJobInstance($jobName, $parameters);

        return $this->jobRepository->createJobExecution($instance, $parameters);
    }

    /**
     * Remove all job executions for the given job name.
     */
    public function removeJobExecutions(string $jobName): void
    {
        foreach ($this->jobRepository->findJobInstancesByName($jobName, 0, 1000) as $instance) {
            $id = $instance->getId();
            if (null === $id) {
                continue;
            }
            $this->jobRepository->deleteJobInstance($id);
        }
    }
}
