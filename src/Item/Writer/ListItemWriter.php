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
 * In-memory list-based writer — useful for tests.
 *
 * @template T
 *
 * @implements ItemWriterInterface<T>
 */
final class ListItemWriter implements ItemWriterInterface
{
    /** @var list<T> */
    private array $writtenItems = [];

    public function clear(): void
    {
        $this->writtenItems = [];
    }

    /**
     * @return list<T>
     */
    public function getWrittenItems(): array
    {
        return $this->writtenItems;
    }

    public function write(Chunk $items): void
    {
        foreach ($items->getOutputItems() as $item) {
            $this->writtenItems[] = $item;
        }
    }
}
