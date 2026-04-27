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

namespace Lemric\BatchProcessing\Bridge\Symfony\Profiler;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters, StepExecution};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Decorator over an arbitrary {@see JobRepositoryInterface} that records every accessed
 * {@see StepExecution}. Read by {@see BatchDataCollector}. All other repository operations
 * are forwarded verbatim.
 */
final class TraceableJobRepository implements JobRepositoryInterface
{
    /** @var array<int, StepExecution> */
    private array $collectedSteps = [];

    public function __construct(private readonly JobRepositoryInterface $delegate)
    {
    }

    public function add(StepExecution $stepExecution): void
    {
        $this->delegate->add($stepExecution);
        $this->trackStep($stepExecution);
    }

    public function createJobExecution(JobInstance $instance, JobParameters $parameters): JobExecution
    {
        return $this->delegate->createJobExecution($instance, $parameters);
    }

    public function createJobInstance(string $jobName, JobParameters $parameters): JobInstance
    {
        return $this->delegate->createJobInstance($jobName, $parameters);
    }

    public function deleteJobExecution(int $executionId): void
    {
        $this->delegate->deleteJobExecution($executionId);
    }

    public function deleteJobInstance(int $instanceId): void
    {
        $this->delegate->deleteJobInstance($instanceId);
    }

    public function findJobExecutions(JobInstance $instance): array
    {
        return $this->delegate->findJobExecutions($instance);
    }

    public function findJobInstancesByName(string $jobName, int $start = 0, int $count = 20): array
    {
        return $this->delegate->findJobInstancesByName($jobName, $start, $count);
    }

    public function findRunningJobExecutions(string $jobName): array
    {
        return $this->delegate->findRunningJobExecutions($jobName);
    }

    /**
     * @return list<StepExecution>
     */
    public function getCollectedSteps(): array
    {
        return array_values($this->collectedSteps);
    }

    public function getJobExecution(int $executionId): ?JobExecution
    {
        return $this->delegate->getJobExecution($executionId);
    }

    public function getJobInstance(int $instanceId): ?JobInstance
    {
        return $this->delegate->getJobInstance($instanceId);
    }

    public function getJobInstanceByJobNameAndParameters(string $jobName, JobParameters $parameters): ?JobInstance
    {
        return $this->delegate->getJobInstanceByJobNameAndParameters($jobName, $parameters);
    }

    public function getJobNames(): array
    {
        return $this->delegate->getJobNames();
    }

    public function getLastJobExecution(JobInstance $instance): ?JobExecution
    {
        return $this->delegate->getLastJobExecution($instance);
    }

    public function getLastJobInstance(string $jobName): ?JobInstance
    {
        return $this->delegate->getLastJobInstance($jobName);
    }

    public function getLastStepExecution(JobInstance $instance, string $stepName): ?StepExecution
    {
        return $this->delegate->getLastStepExecution($instance, $stepName);
    }

    public function getStepExecutionCount(JobInstance $instance, string $stepName): int
    {
        return $this->delegate->getStepExecutionCount($instance, $stepName);
    }

    public function isJobInstanceExists(string $jobName, JobParameters $parameters): bool
    {
        return $this->delegate->isJobInstanceExists($jobName, $parameters);
    }

    public function resetCollection(): void
    {
        $this->collectedSteps = [];
    }

    public function update(StepExecution $stepExecution): void
    {
        $this->delegate->update($stepExecution);
        $this->trackStep($stepExecution);
    }

    public function updateExecutionContext(StepExecution $stepExecution): void
    {
        $this->delegate->updateExecutionContext($stepExecution);
    }

    public function updateJobExecution(JobExecution $jobExecution): void
    {
        $this->delegate->updateJobExecution($jobExecution);
    }

    public function updateJobExecutionContext(JobExecution $jobExecution): void
    {
        $this->delegate->updateJobExecutionContext($jobExecution);
    }

    private function trackStep(StepExecution $stepExecution): void
    {
        $key = $stepExecution->getId() ?? -spl_object_id($stepExecution);
        $this->collectedSteps[$key] = $stepExecution;
    }
}
