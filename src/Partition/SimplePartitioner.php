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
 * Simple partitioner that creates N partitions with sequentially numbered context values.
 * Each partition receives {@code minValue} and {@code maxValue} attributes describing its
 * logical range. The actual range values are supplied as constructor parameters.
 */
final class SimplePartitioner implements PartitionerInterface
{
    public function __construct(
        private readonly int $min = 0,
        private readonly int $max = 0,
    ) {
    }

    /**
     * @return array<string, ExecutionContext>
     */
    public function partition(int $gridSize): array
    {
        $gridSize = max(1, $gridSize);
        $range = (int) ceil(($this->max - $this->min + 1) / $gridSize);
        $partitions = [];

        for ($i = 0; $i < $gridSize; ++$i) {
            $from = $this->min + ($i * $range);
            $to = min($from + $range - 1, $this->max);
            $ctx = new ExecutionContext();
            $ctx->put('minValue', $from);
            $ctx->put('maxValue', $to);
            $partitions["partition{$i}"] = $ctx;

            if ($to >= $this->max) {
                break;
            }
        }

        return $partitions;
    }
}
