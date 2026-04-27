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

namespace Lemric\BatchProcessing\Item\Writer;

use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;
use PDO;

/**
 * Multi-row batch INSERT/UPSERT writer using PDO.
 *
 * Supports named parameters and configurable batch size for efficient database writes.
 *
 * @template T of array<string, mixed>
 *
 * @implements ItemWriterInterface<T>
 */
final class PdoBatchItemWriter implements ItemWriterInterface
{
    /**
     * @param PDO               $pdo
     *                                       "INSERT INTO orders (id, name) VALUES (:id, :name)"
     * @param list<string>|null $columnNames If null, derived from item keys
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $sql,
        private readonly ?array $columnNames = null,
        private readonly bool $assertUpdates = true,
    ) {
    }

    public function write(Chunk $items): void
    {
        $outputItems = $items->getOutputItems();
        if ([] === $outputItems) {
            return;
        }

        $stmt = $this->pdo->prepare($this->sql);

        foreach ($outputItems as $item) {
            $columns = $this->columnNames ?? array_keys($item);
            foreach ($columns as $col) {
                $stmt->bindValue(':'.$col, $item[$col] ?? null);
            }

            $stmt->execute();

            if ($this->assertUpdates && 0 === $stmt->rowCount()) {
                throw new \Lemric\BatchProcessing\Exception\ItemWriterException('PdoBatchItemWriter: statement affected 0 rows.');
            }
        }
    }
}
