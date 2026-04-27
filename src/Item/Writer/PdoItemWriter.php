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
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Exception\WriteFailedException;
use Lemric\BatchProcessing\Security\UnsafeSqlQueryFragmentValidator;
use PDO;
use PDOException;
use PDOStatement;

use function is_bool;
use function is_int;

/**
 * PDO writer using prepared statements. The statement is prepared lazily on the first write
 * and reused throughout the step lifecycle. The writer expects to be invoked inside an open
 * transaction managed by the surrounding {@see \Lemric\BatchProcessing\Step\ChunkOrientedStep}
 * and the framework's {@see \Lemric\BatchProcessing\Transaction\TransactionManagerInterface}.
 *
 * @template TItem
 *
 * @extends AbstractItemWriter<TItem>
 */
final class PdoItemWriter extends AbstractItemWriter
{
    private ?PDOStatement $statement = null;

    /**
     * @param callable(TItem): array<string|int, scalar|null> $itemToParams         maps each item to bound parameters
     * @param bool                                            $validateSqlStatement when true, applies {@see UnsafeSqlQueryFragmentValidator::assertPdoWriterStatement()}
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $sql,
        private $itemToParams,
        private readonly bool $validateSqlStatement = true,
    ) {
        if ($this->validateSqlStatement) {
            UnsafeSqlQueryFragmentValidator::assertPdoWriterStatement(mb_trim($this->sql), 'PdoItemWriter SQL');
        }
    }

    public function close(): void
    {
        if (null !== $this->statement) {
            $this->statement->closeCursor();
            $this->statement = null;
        }
    }

    public function open(ExecutionContext $executionContext): void
    {
        // Reset any cached statement so that a restart re-prepares against a fresh connection.
        $this->statement = null;
    }

    /**
     * @param Chunk<mixed, TItem> $items
     */
    public function write(Chunk $items): void
    {
        if (0 === $items->getOutputCount()) {
            return;
        }

        if (null === $this->statement) {
            try {
                $this->statement = $this->pdo->prepare($this->sql);
            } catch (PDOException $e) {
                throw new WriteFailedException('Failed to prepare writer statement.', previous: $e);
            }
        }
        $statement = $this->statement;

        foreach ($items->getOutputItems() as $item) {
            $params = ($this->itemToParams)($item);
            $this->bindParameters($statement, $params);

            try {
                $statement->execute();
            } catch (PDOException $e) {
                throw new WriteFailedException('Failed to write item.', previous: $e);
            }
        }
    }

    /**
     * @param array<string|int, scalar|null> $params
     */
    private function bindParameters(PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                null === $value => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($param, $value, $type);
        }
    }
}
