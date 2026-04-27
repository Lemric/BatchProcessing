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

use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemStreamInterface};

/**
 * In-memory list-based reader — useful for tests and simple transformations.
 *
 * @template T
 *
 * @implements ItemReaderInterface<T>
 */
final class ListItemReader implements ItemReaderInterface, ItemStreamInterface
{
    private int $index = 0;

    /**
     * @param list<T> $items
     */
    public function __construct(private readonly array $items)
    {
    }

    public function close(): void
    {
        $this->index = 0;
    }

    public function open(ExecutionContext $executionContext): void
    {
        $this->index = $executionContext->getInt('ListItemReader.index', 0);
    }

    public function read(): mixed
    {
        return $this->items[$this->index++] ?? null;
    }

    public function update(ExecutionContext $executionContext): void
    {
        $executionContext->put('ListItemReader.index', $this->index);
    }
}
