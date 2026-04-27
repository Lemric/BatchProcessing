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

namespace Lemric\BatchProcessing\Job;

use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution, JobParameters};
use Lemric\BatchProcessing\Exception\JobInterruptedException;
use Lemric\BatchProcessing\Launcher\SignalHandler;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Default {@see JobInterruptionPolicyInterface}: respects the in-process {@see SignalHandler}
 * (which mutates the {@see JobExecution} directly) and re-reads the {@see JobExecution} from
 * persistence to honour external stop requests issued via {@code JobOperator::stop()}.
 */
final class SignalJobInterruptionPolicy implements JobInterruptionPolicyInterface
{
    public function __construct(private readonly ?SignalHandler $signalHandler = null)
    {
    }

    public function checkInterrupted(JobExecution $jobExecution, JobRepositoryInterface $repository, JobParameters $parameters): void
    {
        // The optional SignalHandler reference is kept solely so DI containers can wire the
        // dependency and ensure the handler is registered for the lifetime of the policy.
        if (null !== $this->signalHandler && $jobExecution->isStopping()) {
            throw new JobInterruptedException('Job interrupted (signal handler requested stop).');
        }
        if ($jobExecution->isStopping()) {
            throw new JobInterruptedException('Job interrupted (stopping flag set in-process).');
        }

        $id = $jobExecution->getId();
        if (null !== $id) {
            $fresh = $repository->getJobExecution($id);
            if (null !== $fresh && (BatchStatus::STOPPING === $fresh->getStatus() || $fresh->isStopping())) {
                $jobExecution->stop();
                throw new JobInterruptedException("Job execution {$id} stopped externally.");
            }
        }
    }
}
