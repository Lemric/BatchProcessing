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

namespace Lemric\BatchProcessing\Item;

/**
 * Strategy for reading items from an input source.
 *
 * Contract:
 *  - {@see read()} MUST return {@code null} once the source is exhausted (end-of-data).
 *  - Each invocation must return the next element or {@code null}.
 *  - Implementations must be safe to use across restarts: after the {@see ItemStreamInterface::open()}
 *    call, an implementing reader should resume from the last persisted checkpoint.
 *  - Implementations are NOT required to be thread/fiber safe (one Step = one execution thread).
 *
 * @template TItem
 */
interface ItemReaderInterface
{
    /**
     * Reads the next item from the source, or returns {@code null} when no more items are available.
     *
     * @return TItem|null
     */
    public function read(): mixed;
}
