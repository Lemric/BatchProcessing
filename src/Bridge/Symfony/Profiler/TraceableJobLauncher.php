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

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};
use Lemric\BatchProcessing\Job\JobInterface;
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Throwable;

/**
 * Decorator over an arbitrary {@see JobLauncherInterface} that records every launched
 * {@see JobExecution} (and any thrown exception). Read by {@see BatchDataCollector}.
 */
final class TraceableJobLauncher implements JobLauncherInterface
{
    /** @var list<string> */
    private array $collectedErrors = [];

    /** @var list<JobExecution> */
    private array $collectedExecutions = [];

    public function __construct(private readonly JobLauncherInterface $delegate)
    {
    }

    /**
     * @return list<string>
     */
    public function getCollectedErrors(): array
    {
        return $this->collectedErrors;
    }

    /**
     * @return list<JobExecution>
     */
    public function getCollectedExecutions(): array
    {
        return $this->collectedExecutions;
    }

    public function resetCollection(): void
    {
        $this->collectedExecutions = [];
        $this->collectedErrors = [];
    }

    public function run(JobInterface $job, JobParameters $parameters): JobExecution
    {
        try {
            $execution = $this->delegate->run($job, $parameters);
            $this->collectedExecutions[] = $execution;

            return $execution;
        } catch (Throwable $e) {
            $this->collectedErrors[] = sprintf('[%s] %s: %s', $job->getName(), get_class($e), $e->getMessage());
            throw $e;
        }
    }
}
