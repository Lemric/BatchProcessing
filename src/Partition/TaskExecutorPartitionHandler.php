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

namespace Lemric\BatchProcessing\Partition;

use Lemric\BatchProcessing\Step\StepInterface;

/**
 * Sequential partition handler: runs each partition one after another in the current process.
 * Suitable for I/O-bound workloads or environments where concurrency is unavailable.
 */
final class TaskExecutorPartitionHandler implements StepHandlerInterface
{
    public function handle(StepInterface $step, array $partitionStepExecutions): void
    {
        foreach ($partitionStepExecutions as $stepExecution) {
            $step->execute($stepExecution);
        }
    }
}
