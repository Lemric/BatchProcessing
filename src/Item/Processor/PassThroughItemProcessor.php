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
 * Null-object processor: returns the item unchanged. Used by default when no processor is
 * configured for a chunk-oriented step.
 *
 * @template TItem
 *
 * @implements ItemProcessorInterface<TItem, TItem>
 */
final class PassThroughItemProcessor implements ItemProcessorInterface
{
    public function process(mixed $item): mixed
    {
        return $item;
    }
}
