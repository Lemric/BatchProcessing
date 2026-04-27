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

namespace Lemric\BatchProcessing\Bridge\Symfony\Lock;

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};
use Lemric\BatchProcessing\Job\JobInterface;
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Decorates an arbitrary {@see JobLauncherInterface} with a Symfony Lock acquisition keyed
 * by {@code jobName + identifying-parameters-hash}. Prevents concurrent launches of the same
 * logical job instance across processes / hosts.
 *
 * Requires {@code symfony/lock} (suggested dependency).
 */
final readonly class LockingJobLauncher implements JobLauncherInterface
{
    public function __construct(
        private JobLauncherInterface $delegate,
        private LockFactory $lockFactory,
        private float $ttlSeconds = 300.0,
        private bool $blocking = false,
    ) {
    }

    public function run(JobInterface $job, JobParameters $parameters): JobExecution
    {
        $key = sprintf('lemric.batch.%s.%s', $job->getName(), $parameters->toJobKey());
        $lock = $this->lockFactory->createLock($key, $this->ttlSeconds, false);

        if (!$lock->acquire($this->blocking)) {
            throw new \Lemric\BatchProcessing\Exception\JobExecutionAlreadyRunningException(sprintf('Job "%s" is already running on another worker (lock %s).', $job->getName(), $key));
        }

        try {
            return $this->delegate->run($job, $parameters);
        } finally {
            $lock->release();
        }
    }
}
