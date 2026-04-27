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

/**
 * Default splitter: creates sub-StepExecutions using a Partitioner, supports restart by
 * recognizing already-completed partitions.
 */
final class SimpleStepExecutionSplitter implements StepExecutionSplitterInterface
{
    public function __construct(
        private readonly PartitionerInterface $partitioner,
        private readonly string $stepName = 'partition',
    ) {
    }

    public function split(StepExecution $masterStepExecution, int $gridSize): array
    {
        $partitions = $this->partitioner->partition($gridSize);
        $result = [];

        foreach ($partitions as $partitionName => $ctx) {
            $subExecution = $masterStepExecution->getJobExecution()->createStepExecution(
                $this->stepName.':'.$partitionName,
            );
            foreach ($ctx->toArray() as $key => $value) {
                $subExecution->getExecutionContext()->putMixed($key, $value);
            }
            $result[$partitionName] = $subExecution;
        }

        return $result;
    }
}
