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
use Lemric\BatchProcessing\Security\SqlIdentifierValidator;
use PDO;

/**
 * PDO-based partitioner that queries MIN/MAX of a numeric column and splits the range
 * into N partitions. Each partition's {@see ExecutionContext} contains `minValue` and
 * `maxValue` keys that a worker step can use to restrict its SQL query.
 */
final class ColumnRangePartitioner implements PartitionerInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table,
        private readonly string $column,
    ) {
        SqlIdentifierValidator::assertValidTableName($this->table, 'partition table');
        SqlIdentifierValidator::assertValidIdentifier($this->column, 'partition column');
    }

    /**
     * @return array<string, ExecutionContext>
     */
    public function partition(int $gridSize): array
    {
        $gridSize = max(1, $gridSize);

        $result = $this->pdo->query(
            "SELECT MIN({$this->column}) AS min_val, MAX({$this->column}) AS max_val FROM {$this->table}",
        );
        if (false === $result) {
            $ctx = new ExecutionContext();
            $ctx->put('minValue', 0);
            $ctx->put('maxValue', 0);

            return ['partition0' => $ctx];
        }

        /** @var array{min_val: int|string|null, max_val: int|string|null}|false $row */
        $row = $result->fetch(PDO::FETCH_ASSOC);

        if (false === $row || null === $row['min_val'] || null === $row['max_val']) {
            // Empty table → single empty partition.
            $ctx = new ExecutionContext();
            $ctx->put('minValue', 0);
            $ctx->put('maxValue', 0);

            return ['partition0' => $ctx];
        }

        $min = (int) $row['min_val'];
        $max = (int) $row['max_val'];

        $range = (int) ceil(($max - $min + 1) / $gridSize);
        $partitions = [];

        for ($i = 0; $i < $gridSize; ++$i) {
            $from = $min + ($i * $range);
            if ($from > $max) {
                break;
            }
            $to = min($from + $range - 1, $max);
            $ctx = new ExecutionContext();
            $ctx->put('minValue', $from);
            $ctx->put('maxValue', $to);
            $partitions["partition{$i}"] = $ctx;

            if ($to >= $max) {
                break;
            }
        }

        return $partitions;
    }
}
