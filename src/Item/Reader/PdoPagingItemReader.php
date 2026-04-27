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
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemStreamInterface};
use Lemric\BatchProcessing\Item\Reader\Paging\SqlPagingQueryProviderInterface;
use PDO;

/**
 * Page-based PDO reader. Fetches one page at a time using a dialect-specific query provider.
 *
 * @implements ItemReaderInterface<array<string, mixed>>
 */
final class PdoPagingItemReader implements ItemReaderInterface, ItemStreamInterface
{
    public const int MAX_PAGE_SIZE = 10_000;

    private int $currentIndex = 0;

    private int $currentPage = 0;

    /** @var list<array<string, mixed>> */
    private array $currentPageItems = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly SqlPagingQueryProviderInterface $queryProvider,
        private readonly int $pageSize = 100,
        /** @var array<string, mixed> */
        private readonly array $parameters = [],
    ) {
        if ($this->pageSize < 1 || $this->pageSize > self::MAX_PAGE_SIZE) {
            throw new InvalidArgumentException('pageSize must be between 1 and '.self::MAX_PAGE_SIZE.'.');
        }
    }

    public function close(): void
    {
        $this->currentPageItems = [];
        $this->currentPage = 0;
        $this->currentIndex = 0;
    }

    public function open(ExecutionContext $executionContext): void
    {
        $this->currentPage = $executionContext->getInt('PdoPagingItemReader.page', 0);
        $this->currentIndex = $executionContext->getInt('PdoPagingItemReader.index', 0);

        if ($this->currentPage > 0 || $this->currentIndex > 0) {
            $this->fetchPage($this->currentPage);
        }
    }

    public function read(): mixed
    {
        if ($this->currentIndex >= count($this->currentPageItems)) {
            $this->fetchNextPage();
            if ([] === $this->currentPageItems) {
                return null;
            }
        }

        return $this->currentPageItems[$this->currentIndex++] ?? null;
    }

    public function update(ExecutionContext $executionContext): void
    {
        $executionContext->put('PdoPagingItemReader.page', $this->currentPage);
        $executionContext->put('PdoPagingItemReader.index', $this->currentIndex);
    }

    private function fetchNextPage(): void
    {
        $this->fetchPage($this->currentPage);
        ++$this->currentPage;
    }

    private function fetchPage(int $page): void
    {
        $offset = $page * $this->pageSize;
        $sql = $this->queryProvider->generateQuery($offset, $this->pageSize);
        $stmt = $this->pdo->prepare($sql);

        foreach ($this->parameters as $name => $value) {
            $stmt->bindValue($name, $value);
        }

        $stmt->execute();
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->currentPageItems = $rows;
        $this->currentIndex = 0;
    }
}
