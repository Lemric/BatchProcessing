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
 * Routes each item to a specific delegate writer based on a classifier function. This allows
 * a single chunk-oriented step to write heterogeneous items to different destinations.
 *
 * The classifier callable receives an item and returns the {@see ItemWriterInterface} instance
 * responsible for writing that item.
 *
 * @template TItem
 *
 * @implements ItemWriterInterface<TItem>
 */
final class ClassifierCompositeItemWriter implements ItemWriterInterface
{
    /**
     * @param callable(TItem): ItemWriterInterface<TItem> $classifier
     */
    public function __construct(private $classifier)
    {
    }

    /**
     * @param Chunk<mixed, TItem> $items
     */
    public function write(Chunk $items): void
    {
        /** @var array<string, array{writer: ItemWriterInterface<TItem>, items: list<TItem>}> $groups */
        $groups = [];

        foreach ($items->getOutputItems() as $item) {
            $writer = ($this->classifier)($item);
            $key = spl_object_id($writer);
            if (!isset($groups[$key])) {
                $groups[$key] = ['writer' => $writer, 'items' => []];
            }
            $groups[$key]['items'][] = $item;
        }

        foreach ($groups as $group) {
            $group['writer']->write(new Chunk([], $group['items']));
        }
    }
}
