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

namespace Lemric\BatchProcessing\Item\Reader;

use Lemric\BatchProcessing\Exception\NonTransientResourceException;
use Lemric\BatchProcessing\Security\UnsafeSqlQueryFragmentValidator;
use PDO;
use PDOException;
use PDOStatement;

use function assert;
use function is_int;

/**
 * Cursor-based PDO reader. Executes the SQL once and reads rows one at a time using
 * {@see PDOStatement::fetch()}, keeping memory usage independent of the result set size.
 *
 * On restart the reader resumes from the previously persisted offset by re-executing the SQL
 * and skipping the already-read rows. For better performance on large datasets, use a SQL
 * predicate that takes advantage of an indexed column rather than relying on offset-based
 * skipping (consider {@see PaginatedPdoItemReader} or a custom reader using a checkpoint
 * column persisted in the {@see ExecutionContext}).
 *
 * @template TItem
 *
 * @extends AbstractItemReader<TItem>
 */
final class PdoItemReader extends AbstractItemReader
{
    private ?PDOStatement $statement = null;

    /**
     * @param array<int|string, scalar|null> $parameters
     * @param int                            $fetchMode            PDO::FETCH_*
     * @param (callable(mixed): TItem)|null  $rowMapper            optional mapping from raw row to TItem
     * @param bool                           $validateSqlStatement when true, applies {@see UnsafeSqlQueryFragmentValidator::assertPdoSelectLikeStatement()} (disable only for trusted static SQL)
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $sql,
        private readonly array $parameters = [],
        private readonly int $fetchMode = PDO::FETCH_ASSOC,
        private $rowMapper = null,
        ?string $name = null,
        bool $saveState = true,
        private readonly bool $validateSqlStatement = true,
    ) {
        if ($this->validateSqlStatement) {
            UnsafeSqlQueryFragmentValidator::assertPdoSelectLikeStatement(mb_trim($this->sql), 'PdoItemReader SQL');
        }
        parent::__construct($name, $saveState);
    }

    protected function doClose(): void
    {
        if (null !== $this->statement) {
            $this->statement->closeCursor();
            $this->statement = null;
        }
    }

    protected function doOpen(): void
    {
        try {
            $this->statement = $this->pdo->prepare($this->sql);
            foreach ($this->parameters as $key => $value) {
                $this->statement->bindValue(is_int($key) ? $key + 1 : $key, $value);
            }
            $this->statement->execute();
        } catch (PDOException $e) {
            throw new NonTransientResourceException('Failed to execute reader SQL.', previous: $e);
        }
    }

    protected function doRead(): mixed
    {
        if (null === $this->statement) {
            $this->doOpen();
        }
        assert(null !== $this->statement);

        $row = $this->statement->fetch($this->fetchMode);
        if (false === $row) {
            return null;
        }

        if (null !== $this->rowMapper) {
            return ($this->rowMapper)($row);
        }

        return $row;
    }
}
