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

use Lemric\BatchProcessing\Domain\ExecutionContext;

/**
 * Splits a dataset into N partitions for parallel processing. Each partition is described by
 * an {@see ExecutionContext} capturing range/filter parameters that a worker step uses to
 * select its subset of the data.
 */
interface PartitionerInterface
{
    /**
     * @param int $gridSize requested number of partitions
     *
     * @return array<string, ExecutionContext> partition-name → context with range boundaries
     */
    public function partition(int $gridSize): array;
}
