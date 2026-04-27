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

namespace Lemric\BatchProcessing\Testing;

use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;
use RuntimeException;

/**
 * Test {@see ItemWriterInterface} that simply appends every received output item to an internal
 * list, optionally failing on the N-th invocation. Useful for unit testing chunk-oriented steps
 * without a real persistence layer.
 *
 * @template TItem
 *
 * @implements ItemWriterInterface<TItem>
 */
final class InMemoryItemWriter implements ItemWriterInterface
{
    /** @var list<TItem> */
    private array $items = [];

    private int $writeCount = 0;

    /**
     * @param int|null $failOnInvocation 1-based invocation number that should raise; null = never
     */
    public function __construct(
        private ?int $failOnInvocation = null,
        private readonly string $failureMessage = 'Simulated write failure',
    ) {
    }

    public function disableFailures(): void
    {
        $this->failOnInvocation = null;
    }

    public function getWriteCount(): int
    {
        return $this->writeCount;
    }

    /**
     * @return list<TItem>
     */
    public function getWrittenItems(): array
    {
        return $this->items;
    }

    public function reset(): void
    {
        $this->items = [];
        $this->writeCount = 0;
        $this->failOnInvocation = null;
    }

    /**
     * @param Chunk<mixed, TItem> $items
     */
    public function write(Chunk $items): void
    {
        ++$this->writeCount;
        if (null !== $this->failOnInvocation && $this->writeCount === $this->failOnInvocation) {
            throw new RuntimeException($this->failureMessage);
        }
        foreach ($items->getOutputItems() as $item) {
            $this->items[] = $item;
        }
    }
}
