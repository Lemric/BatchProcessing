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

namespace Lemric\BatchProcessing\Registry;

use Lemric\BatchProcessing\Exception\{DuplicateJobException, NoSuchJobException};
use Lemric\BatchProcessing\Job\JobInterface;

/**
 * In-memory {@see JobRegistryInterface}. Jobs may be registered as instances or as callable
 * factories; the factory is invoked lazily on the first {@see getJob()} lookup.
 */
final class InMemoryJobRegistry implements JobRegistryInterface
{
    /** @var array<string, JobInterface|callable(): JobInterface> */
    private array $entries = [];

    public function getJob(string $jobName): JobInterface
    {
        if (!isset($this->entries[$jobName])) {
            throw new NoSuchJobException("No job named '{$jobName}' is registered.");
        }
        $entry = $this->entries[$jobName];
        if ($entry instanceof JobInterface) {
            return $entry;
        }

        $job = self::invokeJobFactory($entry);
        if (!$job instanceof JobInterface) {
            throw new NoSuchJobException("Job factory for '{$jobName}' did not return a JobInterface.");
        }
        $this->entries[$jobName] = $job;

        return $job;
    }

    public function getJobNames(): array
    {
        return array_keys($this->entries);
    }

    public function hasJob(string $jobName): bool
    {
        return isset($this->entries[$jobName]);
    }

    /**
     * @throws DuplicateJobException if a job with the same name is already registered
     */
    public function register(string $jobName, JobInterface|callable $jobOrFactory, bool $allowOverride = false): void
    {
        if (!$allowOverride && isset($this->entries[$jobName])) {
            throw new DuplicateJobException("A job named '{$jobName}' is already registered.");
        }
        $this->entries[$jobName] = $jobOrFactory;
    }

    /**
     * @param callable(): mixed $factory
     */
    private static function invokeJobFactory(callable $factory): mixed
    {
        return $factory();
    }
}
