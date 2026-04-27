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

namespace Lemric\BatchProcessing\Tests\Partition;

use InvalidArgumentException;
use Lemric\BatchProcessing\Partition\ColumnRangePartitioner;
use PDO;
use PHPUnit\Framework\TestCase;

final class ColumnRangePartitionerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, name TEXT)');
    }

    public function testGridSizeOne(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $this->pdo->exec("INSERT INTO orders (id, name) VALUES ({$i}, 'X')");
        }

        $partitioner = new ColumnRangePartitioner($this->pdo, 'orders', 'id');
        $partitions = $partitioner->partition(1);

        self::assertCount(1, $partitions);
        self::assertSame(1, $partitions['partition0']->getInt('minValue'));
        self::assertSame(10, $partitions['partition0']->getInt('maxValue'));
    }

    public function testPartitionsEmptyTable(): void
    {
        $partitioner = new ColumnRangePartitioner($this->pdo, 'orders', 'id');
        $partitions = $partitioner->partition(4);

        self::assertCount(1, $partitions);
        self::assertArrayHasKey('partition0', $partitions);
        self::assertSame(0, $partitions['partition0']->getInt('minValue'));
        self::assertSame(0, $partitions['partition0']->getInt('maxValue'));
    }

    public function testPartitionsMultipleRows(): void
    {
        for ($i = 1; $i <= 100; ++$i) {
            $this->pdo->exec("INSERT INTO orders (id, name) VALUES ({$i}, 'Item{$i}')");
        }

        $partitioner = new ColumnRangePartitioner($this->pdo, 'orders', 'id');
        $partitions = $partitioner->partition(4);

        self::assertGreaterThanOrEqual(1, count($partitions));
        self::assertLessThanOrEqual(4, count($partitions));

        // First partition should start at 1.
        self::assertSame(1, $partitions['partition0']->getInt('minValue'));

        // Last partition should end at or past 100.
        $lastKey = array_key_last($partitions);
        self::assertNotNull($lastKey);
        self::assertGreaterThanOrEqual(100, $partitions[$lastKey]->getInt('maxValue'));

        // Partitions should not overlap and cover the full range.
        $prev = null;
        foreach ($partitions as $ctx) {
            $min = $ctx->getInt('minValue');
            $max = $ctx->getInt('maxValue');
            self::assertLessThanOrEqual($max, $min, 'Partition min must not exceed max.');
            if (null !== $prev) {
                self::assertSame($prev + 1, $min, 'Partitions should be contiguous.');
            }
            $prev = $max;
        }
    }

    public function testPartitionsSingleRow(): void
    {
        $this->pdo->exec("INSERT INTO orders (id, name) VALUES (5, 'A')");

        $partitioner = new ColumnRangePartitioner($this->pdo, 'orders', 'id');
        $partitions = $partitioner->partition(4);

        self::assertCount(1, $partitions);
        self::assertSame(5, $partitions['partition0']->getInt('minValue'));
        self::assertSame(5, $partitions['partition0']->getInt('maxValue'));
    }

    public function testRejectsInvalidTableName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ColumnRangePartitioner($this->pdo, 'orders;drop', 'id');
    }
}
