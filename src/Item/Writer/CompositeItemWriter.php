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

use InvalidArgumentException;
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;

/**
 * Composite writer that fans out the chunk to every delegate writer.
 *
 * @template TItem
 *
 * @implements ItemWriterInterface<TItem>
 */
final class CompositeItemWriter implements ItemWriterInterface
{
    /** @var list<ItemWriterInterface<TItem>> */
    private array $delegates;

    /**
     * @param iterable<ItemWriterInterface<TItem>> $delegates
     */
    public function __construct(iterable $delegates)
    {
        $list = [];
        foreach ($delegates as $delegate) {
            $list[] = $delegate;
        }
        if ([] === $list) {
            throw new InvalidArgumentException('CompositeItemWriter requires at least one delegate.');
        }
        $this->delegates = $list;
    }

    /**
     * @param Chunk<mixed, TItem> $items
     */
    public function write(Chunk $items): void
    {
        foreach ($this->delegates as $delegate) {
            $delegate->write($items);
        }
    }
}
