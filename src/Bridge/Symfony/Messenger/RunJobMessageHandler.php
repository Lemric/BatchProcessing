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

namespace Lemric\BatchProcessing\Bridge\Symfony\Messenger;

use Lemric\BatchProcessing\Exception\JobExecutionException;
use Lemric\BatchProcessing\Launcher\SimpleJobLauncher;
use Lemric\BatchProcessing\Registry\JobRegistryInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Security\AsyncJobMessageSigner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Symfony Messenger handler that resumes a previously persisted {@see \Lemric\BatchProcessing\Domain\JobExecution}
 * and runs it synchronously inside the worker process.
 *
 * The handler relies on the **synchronous** {@see SimpleJobLauncher} so that the worker
 * process actually executes the job (the async launcher would just re-dispatch the message,
 * causing an infinite loop).
 */
#[AsMessageHandler]
final readonly class RunJobMessageHandler
{
    public function __construct(
        private JobRegistryInterface $registry,
        private JobRepositoryInterface $repository,
        private SimpleJobLauncher $syncLauncher,
        private string $asyncMessageSecret = '',
        private int $asyncMessageMaxAgeSeconds = AsyncJobMessageSigner::DEFAULT_MAX_MESSAGE_AGE_SECONDS,
    ) {
    }

    public function __invoke(RunJobMessage $message): void
    {
        AsyncJobMessageSigner::verifyOrFail(
            $this->asyncMessageSecret,
            $message->jobExecutionId,
            $message->jobName,
            $message->signature,
            $message->messageIssuedAt,
            max(60, $this->asyncMessageMaxAgeSeconds),
            $message->parametersJobKey,
        );

        $execution = $this->repository->getJobExecution($message->jobExecutionId);
        if (null === $execution) {
            throw new JobExecutionException("Cannot resume: JobExecution {$message->jobExecutionId} not found.");
        }
        if ($execution->getJobName() !== $message->jobName) {
            throw new JobExecutionException(sprintf('JobExecution %d belongs to job "%s" but message references "%s".', $message->jobExecutionId, $execution->getJobName(), $message->jobName));
        }
        if (null !== $message->parametersJobKey && $execution->getJobParameters()->toJobKey() !== $message->parametersJobKey) {
            throw new JobExecutionException(sprintf('JobExecution %d identifying parameters do not match the async message fingerprint.', $message->jobExecutionId));
        }

        $job = $this->registry->getJob($message->jobName);
        $this->syncLauncher->resume($job, $execution);
    }
}
