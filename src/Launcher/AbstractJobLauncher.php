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

namespace Lemric\BatchProcessing\Launcher;

use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution, JobInstance, JobParameters};
use Lemric\BatchProcessing\Exception\{JobExecutionAlreadyRunningException, JobInstanceAlreadyCompleteException, JobRestartException};
use Lemric\BatchProcessing\Job\{AbstractJob, JobInterface};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Psr\Log\{LoggerAwareInterface, LoggerInterface, NullLogger};

/**
 * Base class for job launchers — extracts the common validation and metadata creation logic.
 */
abstract class AbstractJobLauncher implements JobLauncherInterface, LoggerAwareInterface
{
    protected LoggerInterface $logger;

    public function __construct(
        protected readonly JobRepositoryInterface $jobRepository,
    ) {
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Validates that the job is allowed to run with the given parameters and creates
     * a {@see JobExecution}. Throws on duplicate running, already-completed, or non-restartable jobs.
     */
    protected function createValidatedExecution(JobInterface $job, JobParameters $parameters): JobExecution
    {
        $existingInstance = $this->jobRepository->getJobInstanceByJobNameAndParameters($job->getName(), $parameters);

        if (null !== $existingInstance) {
            $this->validateExistingInstance($job, $existingInstance);
            $instance = $existingInstance;
        } else {
            $instance = $this->jobRepository->createJobInstance($job->getName(), $parameters);
        }

        return $this->jobRepository->createJobExecution($instance, $parameters);
    }

    private function validateExistingInstance(JobInterface $job, JobInstance $instance): void
    {
        $lastExecution = $this->jobRepository->getLastJobExecution($instance);
        if (null === $lastExecution) {
            return;
        }

        if ($lastExecution->isRunning()) {
            throw new JobExecutionAlreadyRunningException(sprintf('A job execution for job "%s" is already running.', $job->getName()));
        }

        if (BatchStatus::COMPLETED === $lastExecution->getStatus()) {
            if ($job instanceof AbstractJob && $job->isAllowStartIfComplete()) {
                return;
            }
            throw new JobInstanceAlreadyCompleteException(sprintf('A job instance for "%s" already completed successfully.', $job->getName()));
        }

        if (!$job->isRestartable()) {
            throw new JobRestartException(sprintf('Job "%s" is not restartable.', $job->getName()));
        }
    }
}
