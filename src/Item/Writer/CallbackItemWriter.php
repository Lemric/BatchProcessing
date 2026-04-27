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

/**
 * Wraps an arbitrary {@code callable(list<mixed>): void} as an {@see ItemWriterInterface}.
 *
 * @template TItem
 *
 * @implements ItemWriterInterface<TItem>
 */
final class CallbackItemWriter implements ItemWriterInterface
{
    /**
     * @param callable(list<TItem>): void $callback
     */
    public function __construct(private $callback)
    {
    }

    /**
     * @param Chunk<mixed, TItem> $items
     */
    public function write(Chunk $items): void
    {
        ($this->callback)($items->getOutputItems());
    }
}
