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
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

use const PHP_INT_MAX;

/**
 * Reference {@see JobExplorerInterface} implementation that delegates to a {@see JobRepositoryInterface}.
 *
 * Note: the bundled implementation can only enumerate job names that have been previously
 * launched (since they show up in the {@code job_instance} table). Frameworks integrating with
 * a job registry should override this method to enumerate the registered jobs.
 */
final class SimpleJobExplorer implements JobExplorerInterface
{
    /**
     * @param list<string> $knownJobNames optional pre-configured list of known job names
     *                                    (e.g. from a JobRegistry). When empty, falls back to
     *                                    the repository's {@see JobRepositoryInterface::getJobNames()}.
     */
    public function __construct(
        private readonly JobRepositoryInterface $jobRepository,
        private readonly array $knownJobNames = [],
    ) {
    }

    public function findJobInstancesByJobName(string $jobName, int $start, int $count): array
    {
        return $this->getJobInstances($jobName, $start, $count);
    }

    public function findRunningJobExecutions(string $jobName): array
    {
        return $this->jobRepository->findRunningJobExecutions($jobName);
    }

    public function getJobExecution(int $executionId): ?JobExecution
    {
        return $this->jobRepository->getJobExecution($executionId);
    }

    public function getJobExecutionCount(string $jobName): int
    {
        $instances = $this->jobRepository->findJobInstancesByName($jobName, 0, PHP_INT_MAX);
        $count = 0;
        foreach ($instances as $instance) {
            $count += count($this->jobRepository->findJobExecutions($instance));
        }

        return $count;
    }

    public function getJobExecutions(JobInstance $instance): array
    {
        return $this->jobRepository->findJobExecutions($instance);
    }

    public function getJobInstance(int $instanceId): ?JobInstance
    {
        return $this->jobRepository->getJobInstance($instanceId);
    }

    public function getJobInstanceCount(string $jobName): int
    {
        return count($this->jobRepository->findJobInstancesByName($jobName, 0, PHP_INT_MAX));
    }

    public function getJobInstances(string $jobName, int $start = 0, int $count = 20): array
    {
        return $this->jobRepository->findJobInstancesByName($jobName, $start, $count);
    }

    public function getJobNames(): array
    {
        if ([] !== $this->knownJobNames) {
            return $this->knownJobNames;
        }

        return $this->jobRepository->getJobNames();
    }

    public function getStepExecution(int $jobExecutionId, int $stepExecutionId): ?StepExecution
    {
        $execution = $this->jobRepository->getJobExecution($jobExecutionId);
        if (null === $execution) {
            return null;
        }

        return array_find($execution->getStepExecutions(), fn ($step) => $step->getId() === $stepExecutionId);
    }
}
