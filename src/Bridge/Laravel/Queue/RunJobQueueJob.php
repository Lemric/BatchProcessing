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

namespace Lemric\BatchProcessing\Bridge\Laravel\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Lemric\BatchProcessing\Exception\JobExecutionException;
use Lemric\BatchProcessing\Launcher\SimpleJobLauncher;
use Lemric\BatchProcessing\Registry\JobRegistryInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Security\AsyncJobMessageSigner;

/**
 * Queueable Laravel job that resumes a persisted batch {@see \Lemric\BatchProcessing\Domain\JobExecution}
 * inside the worker process via the **synchronous** {@see SimpleJobLauncher}.
 *
 * Pushed onto the queue by {@see QueueJobDispatcher}.
 */
final class RunJobQueueJob implements ShouldQueue
{
    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public function __construct(
        public readonly int $jobExecutionId,
        public readonly string $jobName,
        public readonly int $messageIssuedAt,
        public readonly ?string $messageSignature = null,
        public readonly ?string $parametersJobKey = null,
    ) {
    }

    public function handle(Container $container): void
    {
        /** @var JobRepositoryInterface $repository */
        $repository = $container->make(JobRepositoryInterface::class);
        /** @var JobRegistryInterface $registry */
        $registry = $container->make(JobRegistryInterface::class);
        /** @var SimpleJobLauncher $launcher */
        $launcher = $container->make(SimpleJobLauncher::class);

        $secret = $this->resolveMessageSecret($container);
        $ttl = $this->resolveMessageTtlSeconds($container);
        AsyncJobMessageSigner::verifyOrFail(
            $secret,
            $this->jobExecutionId,
            $this->jobName,
            $this->messageSignature,
            $this->messageIssuedAt,
            $ttl,
            $this->parametersJobKey,
        );

        $execution = $repository->getJobExecution($this->jobExecutionId);
        if (null === $execution) {
            throw new JobExecutionException("Cannot resume: JobExecution {$this->jobExecutionId} not found.");
        }
        if ($execution->getJobName() !== $this->jobName) {
            throw new JobExecutionException(sprintf('JobExecution %d belongs to job "%s" but queue payload references "%s".', $this->jobExecutionId, $execution->getJobName(), $this->jobName));
        }
        if (null !== $this->parametersJobKey && $execution->getJobParameters()->toJobKey() !== $this->parametersJobKey) {
            throw new JobExecutionException(sprintf('JobExecution %d identifying parameters do not match the async message fingerprint.', $this->jobExecutionId));
        }
        $launcher->resume($registry->getJob($this->jobName), $execution);
    }

    private function resolveMessageSecret(Container $container): string
    {
        if (!$container->bound('config')) {
            return '';
        }
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $container->make('config');
        $async = $config->get('batch_processing.async', []);
        if (!is_array($async)) {
            return '';
        }
        $secret = $async['message_secret'] ?? '';

        return is_string($secret) ? $secret : '';
    }

    private function resolveMessageTtlSeconds(Container $container): int
    {
        if (!$container->bound('config')) {
            return AsyncJobMessageSigner::DEFAULT_MAX_MESSAGE_AGE_SECONDS;
        }
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $container->make('config');
        $async = $config->get('batch_processing.async', []);
        if (!is_array($async)) {
            return AsyncJobMessageSigner::DEFAULT_MAX_MESSAGE_AGE_SECONDS;
        }
        $ttl = $async['message_ttl_seconds'] ?? AsyncJobMessageSigner::DEFAULT_MAX_MESSAGE_AGE_SECONDS;
        if (!is_int($ttl) && !is_numeric($ttl)) {
            return AsyncJobMessageSigner::DEFAULT_MAX_MESSAGE_AGE_SECONDS;
        }
        $ttl = (int) $ttl;

        return max(60, $ttl);
    }
}
