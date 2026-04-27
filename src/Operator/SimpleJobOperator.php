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

namespace Lemric\BatchProcessing\Operator;

use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, JobExecution, JobParameters};
use Lemric\BatchProcessing\Exception\{JobExecutionException, JobExecutionNotRunningException, JobExecutionNotStoppedException, JobParametersInvalidException, NoSuchJobExecutionException, NoSuchJobInstanceException};
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Job\IdentifyingJobParametersValidator;
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Lemric\BatchProcessing\Registry\JobRegistryInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Reference {@see JobOperatorInterface} implementation. Combines a {@see JobRegistryInterface},
 * {@see JobRepositoryInterface}, {@see JobExplorerInterface} and {@see JobLauncherInterface}
 * to provide the standard set of administrative actions.
 */
final readonly class SimpleJobOperator implements JobOperatorInterface
{
    private JobExplorerInterface $explorer;

    public function __construct(
        private JobLauncherInterface $launcher,
        private JobRepositoryInterface $repository,
        private JobRegistryInterface $registry,
        ?JobExplorerInterface $explorer = null,
    ) {
        $this->explorer = $explorer ?? new \Lemric\BatchProcessing\Explorer\SimpleJobExplorer($this->repository);
    }

    public function abandon(int $executionId): JobExecution
    {
        $execution = $this->repository->getJobExecution($executionId);
        if (null === $execution) {
            throw new NoSuchJobExecutionException("No JobExecution with id {$executionId} found.");
        }
        if ($execution->isRunning()) {
            throw new JobExecutionNotStoppedException("Cannot abandon a still-running execution {$executionId}. Stop it first.");
        }
        $execution->setStatus(BatchStatus::ABANDONED);
        $execution->setExitStatus(new ExitStatus(ExitStatus::FAILED_CODE, 'Abandoned'));
        $this->repository->updateJobExecution($execution);

        return $execution;
    }

    public function getExecutions(int $instanceId): array
    {
        $instance = $this->repository->getJobInstance($instanceId);
        if (null === $instance) {
            throw new NoSuchJobInstanceException("No JobInstance with id {$instanceId} found.");
        }

        return array_map(
            static fn ($e): int => (int) $e->getId(),
            $this->repository->findJobExecutions($instance),
        );
    }

    public function getJobExecutionCount(string $jobName): int
    {
        return $this->explorer->getJobExecutionCount($jobName);
    }

    public function getJobInstanceCount(string $jobName): int
    {
        return $this->explorer->getJobInstanceCount($jobName);
    }

    public function getJobInstances(string $jobName, int $start, int $count): array
    {
        return array_map(
            static fn ($i): int => (int) $i->getId(),
            $this->repository->findJobInstancesByName($jobName, $start, $count),
        );
    }

    public function getJobNames(): array
    {
        return $this->registry->getJobNames();
    }

    public function getParameters(int $executionId): string
    {
        $execution = $this->repository->getJobExecution($executionId);
        if (null === $execution) {
            throw new JobExecutionException("No JobExecution with id {$executionId} found.");
        }

        return $execution->getJobParameters()->toIdentifyingString();
    }

    public function getRunningExecutions(string $jobName): array
    {
        return array_map(
            static fn ($e): int => (int) $e->getId(),
            $this->repository->findRunningJobExecutions($jobName),
        );
    }

    public function getStepExecutionSummaries(int $executionId): array
    {
        $execution = $this->repository->getJobExecution($executionId);
        if (null === $execution) {
            throw new NoSuchJobExecutionException("No JobExecution with id {$executionId} found.");
        }

        $summaries = [];
        foreach ($execution->getStepExecutions() as $step) {
            $summaries[(int) $step->getId()] = $step->getSummary();
        }

        return $summaries;
    }

    public function getStepExecutionSummary(int $jobExecutionId, int $stepExecutionId): string
    {
        $summaries = $this->getStepExecutionSummaries($jobExecutionId);

        return $summaries[$stepExecutionId] ?? 'Step execution not found.';
    }

    public function getSummary(int $executionId): string
    {
        $execution = $this->repository->getJobExecution($executionId);
        if (null === $execution) {
            throw new JobExecutionException("No JobExecution with id {$executionId} found.");
        }

        return sprintf(
            'JobExecution[id=%d, jobName=%s, status=%s, exitStatus=%s, startTime=%s, endTime=%s]',
            $execution->getId() ?? 0,
            $execution->getJobName(),
            $execution->getStatus()->value,
            (string) $execution->getExitStatus(),
            $execution->getStartTime()?->format('Y-m-d H:i:s') ?? 'N/A',
            $execution->getEndTime()?->format('Y-m-d H:i:s') ?? 'N/A',
        );
    }

    public function restart(int $executionId): int
    {
        $previous = $this->repository->getJobExecution($executionId);
        if (null === $previous) {
            throw new JobExecutionException("No JobExecution with id {$executionId} found.");
        }
        if (BatchStatus::COMPLETED === $previous->getStatus()) {
            throw new JobExecutionException("JobExecution {$executionId} already COMPLETED.");
        }
        $job = $this->registry->getJob($previous->getJobName());

        try {
            new IdentifyingJobParametersValidator($previous->getJobName(), $this->repository)
                ->validate($previous->getJobParameters());
        } catch (JobParametersInvalidException $e) {
            throw new JobExecutionException($e->getMessage(), previous: $e);
        }

        $execution = $this->launcher->run($job, $previous->getJobParameters());

        return (int) $execution->getId();
    }

    public function start(string $jobName, JobParameters $parameters): int
    {
        $job = $this->registry->getJob($jobName);
        $execution = $this->launcher->run($job, $parameters);

        return (int) $execution->getId();
    }

    /**
     * Convenience entry point: derive the next set of identifying parameters via the job's
     * configured {@see \Lemric\BatchProcessing\Job\JobParametersIncrementerInterface} and
     * launch a fresh {@see \Lemric\BatchProcessing\Domain\JobInstance}.
     *
     * @throws JobExecutionException when the job has no incrementer configured
     */
    public function startNextInstance(string $jobName): int
    {
        $job = $this->registry->getJob($jobName);
        if (!$job instanceof \Lemric\BatchProcessing\Job\AbstractJob || null === $job->getIncrementer()) {
            throw new JobExecutionException("Job '{$jobName}' has no JobParametersIncrementer configured.");
        }
        $lastInstance = $this->repository->getLastJobInstance($jobName);
        $previousParams = null === $lastInstance ? null : $this->repository->getLastJobExecution($lastInstance)?->getJobParameters();
        $next = $job->getIncrementer()->getNext($previousParams);
        $execution = $this->launcher->run($job, $next);

        return (int) $execution->getId();
    }

    public function stop(int $executionId): bool
    {
        $execution = $this->repository->getJobExecution($executionId);
        if (null === $execution) {
            throw new NoSuchJobExecutionException("No JobExecution with id {$executionId} found.");
        }
        if (!$execution->isRunning()) {
            throw new JobExecutionNotRunningException("Execution {$executionId} is not running — cannot stop.");
        }
        $execution->stop();
        $this->repository->updateJobExecution($execution);

        return true;
    }
}
