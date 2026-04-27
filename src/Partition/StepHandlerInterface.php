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

use Lemric\BatchProcessing\Domain\StepExecution;
use Lemric\BatchProcessing\Step\StepInterface;

/**
 * Handler responsible for executing a worker step for each partition. Implementations may run
 * partitions sequentially, concurrently via Fibers, or dispatch them to message queues.
 */
interface StepHandlerInterface
{
    /**
     * Executes the given step for every partition, using the master StepExecution for
     * aggregation and the partition-specific StepExecutions for isolation.
     *
     * @param list<StepExecution> $partitionStepExecutions
     */
    public function handle(StepInterface $step, array $partitionStepExecutions): void;
}
