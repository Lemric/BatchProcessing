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

use ArrayIterator;
use Generator;
use Iterator;
use IteratorAggregate;
use IteratorIterator;
use Lemric\BatchProcessing\Item\ItemReaderInterface;

use function is_array;

/**
 * Reads items from any {@see Iterator} (or generator). Useful for in-memory data, generators
 * over external resources and composing other iterables.
 *
 * @template TItem
 *
 * @implements ItemReaderInterface<TItem>
 */
final class IteratorItemReader implements ItemReaderInterface
{
    /** @var Iterator<mixed, TItem> */
    private Iterator $iterator;

    private bool $started = false;

    /**
     * @param iterable<mixed, TItem> $iterable
     */
    public function __construct(iterable $iterable)
    {
        if ($iterable instanceof Iterator) {
            $this->iterator = $iterable;
        } elseif (is_array($iterable)) {
            /** @var ArrayIterator<int|string, TItem> $iter */
            $iter = new ArrayIterator($iterable);
            $this->iterator = $iter;
        } elseif ($iterable instanceof IteratorAggregate) {
            $inner = $iterable->getIterator();
            /** @var Iterator<mixed, TItem> $iter */
            $iter = $inner instanceof Iterator ? $inner : new IteratorIterator($inner);
            $this->iterator = $iter;
        } else {
            /** @var Iterator<mixed, TItem> $iter */
            $iter = (static function () use ($iterable): Generator {
                foreach ($iterable as $key => $value) {
                    yield $key => $value;
                }
            })();
            $this->iterator = $iter;
        }
    }

    public function read(): mixed
    {
        if (!$this->started) {
            $this->iterator->rewind();
            $this->started = true;
        } else {
            $this->iterator->next();
        }

        if (!$this->iterator->valid()) {
            return null;
        }

        return $this->iterator->current();
    }
}
