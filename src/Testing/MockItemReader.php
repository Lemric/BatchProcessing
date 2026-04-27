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

namespace Lemric\BatchProcessing\Testing;

use Lemric\BatchProcessing\Item\ItemReaderInterface;

use function count;

/**
 * Mock reader returning items from a fixed list. Optionally raises an exception when reaching a
 * particular index, allowing tests to model partial reader failures.
 *
 * @template TItem
 *
 * @implements ItemReaderInterface<TItem>
 */
final class MockItemReader implements ItemReaderInterface
{
    private int $cursor = 0;

    /** @var list<TItem> */
    private array $items;

    /**
     * @param list<TItem> $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * @template TT
     *
     * @param list<TT> $items
     *
     * @return self<TT>
     */
    public static function ofList(array $items): self
    {
        return new self($items);
    }

    public function read(): mixed
    {
        if ($this->cursor >= count($this->items)) {
            return null;
        }

        return $this->items[$this->cursor++];
    }

    public function reset(): void
    {
        $this->cursor = 0;
    }
}
