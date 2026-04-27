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

use Lemric\BatchProcessing\Chunk\Chunk;

/**
 * Strategy for writing a batch (chunk) of items.
 *
 * The writer is invoked once per chunk inside an open transaction managed by the framework.
 *
 * @template TItem
 */
interface ItemWriterInterface
{
    /**
     * @param Chunk<mixed, TItem> $items
     */
    public function write(Chunk $items): void;
}
