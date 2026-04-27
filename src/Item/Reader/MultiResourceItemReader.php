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

use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemStreamInterface};

/**
 * Decorator aggregating multiple resources (files) into a single reader.
 * Automatically switches to the next resource when the current one is exhausted.
 *
 * @template TItem
 *
 * @implements ItemReaderInterface<TItem>
 */
final class MultiResourceItemReader implements ItemReaderInterface, ItemStreamInterface
{
    private const string STATE_ITEM_COUNT = 'multi.item.count';

    private const string STATE_RESOURCE_INDEX = 'multi.resource.index';

    private int $currentResourceIndex = 0;

    private int $itemCount = 0;

    private bool $opened = false;

    /** @var list<string> */
    private array $resources = [];

    /**
     * @param ItemReaderInterface<TItem>&ItemStreamInterface $delegate
     */
    public function __construct(
        private readonly ItemReaderInterface&ItemStreamInterface $delegate,
    ) {
    }

    public function close(): void
    {
        $this->delegate->close();
        $this->opened = false;
        $this->currentResourceIndex = 0;
        $this->itemCount = 0;
    }

    public function open(ExecutionContext $executionContext): void
    {
        if ($executionContext->containsKey(self::STATE_RESOURCE_INDEX)) {
            $this->currentResourceIndex = $executionContext->getInt(self::STATE_RESOURCE_INDEX);
            $this->itemCount = $executionContext->getInt(self::STATE_ITEM_COUNT);
        }

        if ($this->currentResourceIndex < count($this->resources)) {
            $this->openCurrentResource($executionContext);
        }
    }

    public function read(): mixed
    {
        if ($this->currentResourceIndex >= count($this->resources)) {
            return null;
        }

        if (!$this->opened) {
            $this->openCurrentResource(new ExecutionContext());
        }

        $item = $this->delegate->read();

        if (null === $item) {
            $this->delegate->close();
            ++$this->currentResourceIndex;

            if ($this->currentResourceIndex >= count($this->resources)) {
                return null;
            }

            $this->openCurrentResource(new ExecutionContext());
            $item = $this->delegate->read();
        }

        if (null !== $item) {
            ++$this->itemCount;
        }

        return $item;
    }

    /**
     * @param list<string> $resources
     */
    public function setResources(array $resources): void
    {
        $this->resources = $resources;
    }

    public function update(ExecutionContext $executionContext): void
    {
        $executionContext->put(self::STATE_RESOURCE_INDEX, $this->currentResourceIndex);
        $executionContext->put(self::STATE_ITEM_COUNT, $this->itemCount);
        $this->delegate->update($executionContext);
    }

    private function openCurrentResource(ExecutionContext $executionContext): void
    {
        $this->opened = true;
        // Subclasses or the delegate must know how to read from the resource at $this->resources[$this->currentResourceIndex].
        // The delegate's open() is called to initialize it.
        $this->delegate->open($executionContext);
    }
}
