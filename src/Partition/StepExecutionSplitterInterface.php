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
 * Splits a master StepExecution into sub-StepExecutions for each partition.
 */
interface StepExecutionSplitterInterface
{
    /**
     * @return array<string, StepExecution> partitionName => StepExecution
     */
    public function split(StepExecution $masterStepExecution, int $gridSize): array;
}
