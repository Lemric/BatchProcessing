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

/**
 * Base class for cache-decorated {@see JobExplorerInterface} implementations.
 * Subclasses only need to implement {@see remember()} for their specific cache backend.
 *
 * Caching decisions are centralised here:
 *  - Running executions are NEVER cached (real-time requirement).
 *  - Step executions are NOT cached (too granular).
 *  - Counts are NOT cached (cheap to compute, staleness is dangerous).
 *  - Everything else is cached with the configured TTL.
 */
abstract class AbstractCachedJobExplorer implements JobExplorerInterface
{
    public function __construct(
        protected readonly JobExplorerInterface $delegate,
        protected readonly int $ttlSeconds = 60,
    ) {
    }

    public function findJobInstancesByJobName(string $jobName, int $start, int $count): array
    {
        return $this->delegate->findJobInstancesByJobName($jobName, $start, $count);
    }

    public function findRunningJobExecutions(string $jobName): array
    {
        // Running executions should never be cached — must always be real-time.
        return $this->delegate->findRunningJobExecutions($jobName);
    }

    public function getJobExecution(int $executionId): ?JobExecution
    {
        return $this->remember('exec.'.$executionId, fn () => $this->delegate->getJobExecution($executionId));
    }

    public function getJobExecutionCount(string $jobName): int
    {
        return $this->delegate->getJobExecutionCount($jobName);
    }

    public function getJobExecutions(JobInstance $instance): array
    {
        return $this->remember('inst_execs.'.($instance->getId() ?? 0), fn () => $this->delegate->getJobExecutions($instance));
    }

    public function getJobInstance(int $instanceId): ?JobInstance
    {
        return $this->remember('inst.'.$instanceId, fn () => $this->delegate->getJobInstance($instanceId));
    }

    public function getJobInstanceCount(string $jobName): int
    {
        return $this->delegate->getJobInstanceCount($jobName);
    }

    public function getJobInstances(string $jobName, int $start = 0, int $count = 20): array
    {
        return $this->remember("instances.{$jobName}.{$start}.{$count}", fn () => $this->delegate->getJobInstances($jobName, $start, $count));
    }

    public function getJobNames(): array
    {
        return $this->remember('names', fn () => $this->delegate->getJobNames());
    }

    public function getStepExecution(int $jobExecutionId, int $stepExecutionId): ?StepExecution
    {
        // Step executions are granular — delegate without caching.
        return $this->delegate->getStepExecution($jobExecutionId, $stepExecutionId);
    }

    /**
     * @template T
     *
     * @param callable(): T $factory
     *
     * @return T
     */
    abstract protected function remember(string $suffix, callable $factory): mixed;
}
