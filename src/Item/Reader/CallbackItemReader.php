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

use Lemric\BatchProcessing\Item\ItemReaderInterface;

/**
 * Wraps an arbitrary {@code callable(): mixed} as an {@see ItemReaderInterface}. The callback
 * must return {@code null} to signal end-of-data.
 *
 * @template TItem
 *
 * @implements ItemReaderInterface<TItem>
 */
final class CallbackItemReader implements ItemReaderInterface
{
    /**
     * @param callable(): (TItem|null) $callback
     */
    public function __construct(private $callback)
    {
    }

    public function read(): mixed
    {
        return ($this->callback)();
    }
}
