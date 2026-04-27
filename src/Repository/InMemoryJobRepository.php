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

use DateTimeImmutable;
use Lemric\BatchProcessing\Domain\{BatchStatus, ExecutionContext, JobExecution, JobInstance, JobParameters, StepExecution};

use function array_filter;
use function array_values;
use function in_array;

/**
 * In-memory implementation, suitable for unit and integration tests where the metadata schema
 * is not required. NOT thread/process safe and NOT durable.
 */
final class InMemoryJobRepository extends AbstractJobRepository
{
    /** @var array<int, list<int>> instanceId => list<jobExecutionId> */
    private array $instanceExecutions = [];

    /** @var array<int, ExecutionContext> jobExecutionId => ctx */
    private array $jobExecutionContexts = [];

    /** @var array<int, JobExecution> */
    private array $jobExecutions = [];

    private int $jobExecutionSequence = 0;

    /** @var array<int, JobInstance> */
    private array $jobInstances = [];

    /** @var array<string, JobInstance> */
    private array $jobInstancesByKey = [];

    private int $jobInstanceSequence = 0;

    /** @var array<int, ExecutionContext> stepExecutionId => ctx */
    private array $stepExecutionContexts = [];

    private int $stepExecutionSequence = 0;

    public function add(StepExecution $stepExecution): void
    {
        if (null === $stepExecution->getId()) {
            $stepExecution->setId(++$this->stepExecutionSequence);
        }
        $stepExecution->setLastUpdated(new DateTimeImmutable());
    }

    public function createJobExecution(JobInstance $instance, JobParameters $parameters): JobExecution
    {
        $id = ++$this->jobExecutionSequence;
        $execution = new JobExecution($id, $instance, $parameters);
        $execution->setCreateTime(new DateTimeImmutable());
        $this->jobExecutions[$id] = $execution;
        $this->instanceExecutions[$instance->getId() ?? 0][] = $id;

        return $execution;
    }

    public function createJobInstance(string $jobName, JobParameters $parameters): JobInstance
    {
        $key = $parameters->toJobKey();
        $compoundKey = $jobName.'|'.$key;
        if (isset($this->jobInstancesByKey[$compoundKey])) {
            return $this->jobInstancesByKey[$compoundKey];
        }
        $id = ++$this->jobInstanceSequence;
        $instance = new JobInstance($id, $jobName, $key);
        $this->jobInstances[$id] = $instance;
        $this->jobInstancesByKey[$compoundKey] = $instance;

        return $instance;
    }

    public function deleteJobExecution(int $executionId): void
    {
        $execution = $this->jobExecutions[$executionId] ?? null;
        if (null === $execution) {
            return;
        }

        // Remove step execution contexts.
        foreach ($execution->getStepExecutions() as $step) {
            if (null !== $step->getId()) {
                unset($this->stepExecutionContexts[$step->getId()]);
            }
        }

        // Remove from instance→executions mapping.
        $instanceId = $execution->getJobInstance()->getId() ?? 0;
        if (isset($this->instanceExecutions[$instanceId])) {
            $this->instanceExecutions[$instanceId] = array_values(
                array_filter($this->instanceExecutions[$instanceId], static fn (int $id): bool => $id !== $executionId),
            );
        }

        unset($this->jobExecutionContexts[$executionId], $this->jobExecutions[$executionId]);
    }

    public function deleteJobInstance(int $instanceId): void
    {
        $instance = $this->jobInstances[$instanceId] ?? null;
        if (null === $instance) {
            return;
        }

        // Delete all associated executions first.
        $executionIds = $this->instanceExecutions[$instanceId] ?? [];
        foreach ($executionIds as $executionId) {
            $this->deleteJobExecution($executionId);
        }
        unset($this->instanceExecutions[$instanceId]);

        // Remove from key-based lookup.
        $compoundKey = $instance->getJobName().'|'.$instance->getJobKey();
        unset($this->jobInstancesByKey[$compoundKey], $this->jobInstances[$instanceId]);
    }

    public function findJobExecutions(JobInstance $instance): array
    {
        $ids = $this->instanceExecutions[$instance->getId() ?? 0] ?? [];
        $list = [];
        foreach ($ids as $id) {
            if (isset($this->jobExecutions[$id])) {
                $list[] = $this->jobExecutions[$id];
            }
        }
        usort($list, static fn (JobExecution $a, JobExecution $b): int => ($b->getId() ?? 0) <=> ($a->getId() ?? 0));

        return $list;
    }

    public function findJobInstancesByName(string $jobName, int $start = 0, int $count = 20): array
    {
        $result = [];
        foreach ($this->jobInstances as $instance) {
            if ($instance->getJobName() === $jobName) {
                $result[] = $instance;
            }
        }
        usort($result, static fn (JobInstance $a, JobInstance $b): int => ($b->getId() ?? 0) <=> ($a->getId() ?? 0));

        return array_slice($result, $start, $count);
    }

    public function findRunningJobExecutions(string $jobName): array
    {
        $running = [];
        foreach ($this->jobExecutions as $execution) {
            if ($execution->getJobName() === $jobName && $execution->isRunning()) {
                $running[] = $execution;
            }
        }

        return $running;
    }

    public function getJobExecution(int $executionId): ?JobExecution
    {
        return $this->jobExecutions[$executionId] ?? null;
    }

    public function getJobInstance(int $instanceId): ?JobInstance
    {
        return $this->jobInstances[$instanceId] ?? null;
    }

    public function getJobInstanceByJobNameAndParameters(string $jobName, JobParameters $parameters): ?JobInstance
    {
        return $this->jobInstancesByKey[$jobName.'|'.$parameters->toJobKey()] ?? null;
    }

    public function getJobNames(): array
    {
        $names = [];
        foreach ($this->jobInstances as $instance) {
            $name = $instance->getJobName();
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    public function getLastStepExecution(JobInstance $instance, string $stepName): ?StepExecution
    {
        foreach ($this->findJobExecutions($instance) as $execution) {
            foreach (array_reverse($execution->getStepExecutions()) as $step) {
                if ($step->getStepName() === $stepName) {
                    return $step;
                }
            }
        }

        return null;
    }

    public function getStepExecutionCount(JobInstance $instance, string $stepName): int
    {
        $count = 0;
        foreach ($this->findJobExecutions($instance) as $execution) {
            foreach ($execution->getStepExecutions() as $step) {
                if ($step->getStepName() === $stepName) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * Returns the most recently persisted ExecutionContext for the given job execution id (or
     * {@code null} if none was ever stored). Useful for tests asserting checkpoint state.
     */
    public function getStoredJobExecutionContext(int $jobExecutionId): ?ExecutionContext
    {
        $ctx = $this->jobExecutionContexts[$jobExecutionId] ?? null;

        return null === $ctx ? null : clone $ctx;
    }

    public function getStoredStepExecutionContext(int $stepExecutionId): ?ExecutionContext
    {
        $ctx = $this->stepExecutionContexts[$stepExecutionId] ?? null;

        return null === $ctx ? null : clone $ctx;
    }

    public function isJobInstanceExists(string $jobName, JobParameters $parameters): bool
    {
        return isset($this->jobInstancesByKey[$jobName.'|'.$parameters->toJobKey()]);
    }

    public function update(StepExecution $stepExecution): void
    {
        $stepExecution->setLastUpdated(new DateTimeImmutable());
    }

    public function updateExecutionContext(StepExecution $stepExecution): void
    {
        if (null === $stepExecution->getId()) {
            return;
        }
        $this->stepExecutionContexts[$stepExecution->getId()] = clone $stepExecution->getExecutionContext();
        $stepExecution->getExecutionContext()->clearDirtyFlag();
    }

    public function updateJobExecution(JobExecution $jobExecution): void
    {
        $jobExecution->setLastUpdated(new DateTimeImmutable());
        if (null !== $jobExecution->getId()) {
            $this->jobExecutions[$jobExecution->getId()] = $jobExecution;
        }
    }

    public function updateJobExecutionContext(JobExecution $jobExecution): void
    {
        if (null === $jobExecution->getId()) {
            return;
        }
        $this->jobExecutionContexts[$jobExecution->getId()] = clone $jobExecution->getExecutionContext();
        $jobExecution->getExecutionContext()->clearDirtyFlag();
    }

    /**
     * Persists a fresh status update; useful in tests.
     */
    public function updateStatus(JobExecution $execution, BatchStatus $status): void
    {
        $execution->setStatus($status);
        $this->updateJobExecution($execution);
    }
}
