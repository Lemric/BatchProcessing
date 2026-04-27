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

use InvalidArgumentException;
use Lemric\BatchProcessing\Exception\NonTransientResourceException;
use Lemric\BatchProcessing\Security\UnsafeSqlQueryFragmentValidator;
use PDO;

use PDOException;

use function count;
use function is_int;

/**
 * Paginated PDO reader that fetches rows page-by-page using LIMIT/OFFSET. Each page is loaded
 * into memory as a buffer, rows are returned one at a time, and the next page is fetched
 * automatically when the buffer is exhausted.
 *
 * This approach is simpler than a cursor-based reader on databases where unbuffered cursors are
 * unavailable or expensive, but comes at the cost of re-executing the query for each page.
 *
 * The SQL **must** contain an ORDER BY clause to guarantee deterministic pagination.
 *
 * @template TItem
 *
 * @extends AbstractItemReader<TItem>
 */
final class PaginatedPdoItemReader extends AbstractItemReader
{
    /** @var list<mixed> */
    private array $buffer = [];

    private bool $exhausted = false;

    private int $page = 0;

    /**
     * @param array<int|string, scalar|null> $parameters SQL bind parameters
     * @param int                            $pageSize   rows per page
     * @param int                            $fetchMode  PDO::FETCH_*
     * @param (callable(mixed): TItem)|null  $rowMapper  optional mapping from raw row to TItem
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $sql,
        private readonly array $parameters = [],
        private readonly int $pageSize = 100,
        private readonly int $fetchMode = PDO::FETCH_ASSOC,
        private $rowMapper = null,
        ?string $name = null,
        bool $saveState = true,
    ) {
        if ($this->pageSize < 1 || $this->pageSize > 10_000) {
            throw new InvalidArgumentException('pageSize must be between 1 and 10000.');
        }
        $trimmed = mb_trim($sql);
        UnsafeSqlQueryFragmentValidator::assertPaginatedStatementSql($trimmed, 'PaginatedPdoItemReader SQL');
        UnsafeSqlQueryFragmentValidator::assertPdoSelectLikeStatement($trimmed, 'PaginatedPdoItemReader SQL');
        parent::__construct($name, $saveState);
    }

    protected function doClose(): void
    {
        $this->buffer = [];
        $this->page = 0;
        $this->exhausted = false;
    }

    protected function doOpen(): void
    {
        $this->page = 0;
        $this->exhausted = false;
        $this->fetchPage();
    }

    protected function doRead(): mixed
    {
        if ([] === $this->buffer) {
            if ($this->exhausted) {
                return null;
            }
            $this->fetchPage();
            if ([] === $this->buffer) {
                return null;
            }
        }

        $row = array_shift($this->buffer);

        if (null !== $this->rowMapper) {
            return ($this->rowMapper)($row);
        }

        return $row;
    }

    private function fetchPage(): void
    {
        $offset = $this->page * $this->pageSize;
        $paginatedSql = $this->sql." LIMIT {$this->pageSize} OFFSET {$offset}";

        try {
            $stmt = $this->pdo->prepare($paginatedSql);
            foreach ($this->parameters as $key => $value) {
                $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll($this->fetchMode);
            $stmt->closeCursor();
        } catch (PDOException $e) {
            throw new NonTransientResourceException("Failed to fetch page {$this->page}.", previous: $e);
        }

        $this->buffer = array_values($rows);
        ++$this->page;

        if (count($rows) < $this->pageSize) {
            $this->exhausted = true;
        }
    }
}
