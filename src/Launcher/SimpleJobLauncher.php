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

use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution, JobParameters};
use Lemric\BatchProcessing\Exception\JobExecutionException;
use Lemric\BatchProcessing\Job\JobInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Security\SensitiveDataSanitizer;
use Throwable;

/**
 * Synchronous launcher: validates parameters, creates metadata and invokes the job inline.
 */
final class SimpleJobLauncher extends AbstractJobLauncher
{
    public function __construct(JobRepositoryInterface $jobRepository)
    {
        parent::__construct($jobRepository);
    }

    /**
     * Resumes a persisted {@see JobExecution} from an async worker (queue / Messenger).
     * Does not create a new execution. Idempotent for terminal states so poisoned replays are safe.
     */
    public function resume(JobInterface $job, JobExecution $execution): void
    {
        if ($job->getName() !== $execution->getJobName()) {
            throw new JobExecutionException(sprintf('Job name mismatch: worker payload references "%s" but JobExecution %s belongs to job "%s".', $job->getName(), $execution->getId() ?? 'null', $execution->getJobName()));
        }

        $status = $execution->getStatus();
        if (
            BatchStatus::COMPLETED === $status
            || BatchStatus::ABANDONED === $status
            || BatchStatus::FAILED === $status
            || BatchStatus::STOPPED === $status
        ) {
            $this->logger->info('Async job resume skipped: execution already terminal (idempotent)', [
                'job' => $job->getName(),
                'jobExecutionId' => $execution->getId(),
                'status' => $status->value,
            ]);

            return;
        }

        $this->logger->info('Resuming job from async worker', [
            'job' => $job->getName(),
            'jobExecutionId' => $execution->getId(),
        ]);

        try {
            $job->execute($execution);
        } catch (Throwable $e) {
            $this->logger->error('Job execution raised', [
                'job' => $job->getName(),
                'exceptionClass' => $e::class,
                'message' => SensitiveDataSanitizer::sanitize($e->getMessage()),
            ]);
            throw $e;
        }
    }

    public function run(JobInterface $job, JobParameters $parameters): JobExecution
    {
        $execution = $this->createValidatedExecution($job, $parameters);

        $this->logger->info('Launching job', [
            'job' => $job->getName(),
            'jobExecutionId' => $execution->getId(),
        ]);

        try {
            $job->execute($execution);
        } catch (Throwable $e) {
            $this->logger->error('Job execution raised', [
                'job' => $job->getName(),
                'exceptionClass' => $e::class,
                'message' => SensitiveDataSanitizer::sanitize($e->getMessage()),
            ]);
            throw $e;
        }

        return $execution;
    }
}
