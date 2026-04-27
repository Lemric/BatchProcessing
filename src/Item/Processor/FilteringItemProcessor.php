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

namespace Lemric\BatchProcessing\Item\Processor;

use Lemric\BatchProcessing\Item\ItemProcessorInterface;

/**
 * Filters items using a user-supplied predicate. Items for which the predicate returns
 * {@code false} are filtered out (the framework will increment the filter counter).
 *
 * @template TItem
 *
 * @implements ItemProcessorInterface<TItem, TItem>
 */
final class FilteringItemProcessor implements ItemProcessorInterface
{
    /**
     * @param callable(TItem): bool $predicate
     */
    public function __construct(private $predicate)
    {
    }

    public function process(mixed $item): mixed
    {
        return ($this->predicate)($item) ? $item : null;
    }
}
