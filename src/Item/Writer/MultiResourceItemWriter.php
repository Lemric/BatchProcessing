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
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\{ItemStreamInterface, ItemWriterInterface};

/**
 * Decorator that splits output across multiple files based on item count.
 *
 * @template TItem
 *
 * @implements ItemWriterInterface<TItem>
 */
final class MultiResourceItemWriter implements ItemWriterInterface, ItemStreamInterface
{
    private const string STATE_CURRENT_COUNT = 'multi.writer.current.count';

    private const string STATE_RESOURCE_INDEX = 'multi.writer.resource.index';

    private int $currentItemCount = 0;

    private int $currentResourceIndex = 0;

    private bool $opened = false;

    /**
     * @param ItemWriterInterface<TItem>&ItemStreamInterface $delegate
     * @param callable(int): string                          $resourceSuffixCreator     generates file path from resource index
     * @param int                                            $itemCountLimitPerResource max items per file
     */
    public function __construct(
        private readonly ItemWriterInterface&ItemStreamInterface $delegate,
        private $resourceSuffixCreator,
        private readonly int $itemCountLimitPerResource = 1000,
    ) {
    }

    public function close(): void
    {
        $this->delegate->close();
        $this->opened = false;
        $this->currentResourceIndex = 0;
        $this->currentItemCount = 0;
    }

    public function open(ExecutionContext $executionContext): void
    {
        if ($executionContext->containsKey(self::STATE_RESOURCE_INDEX)) {
            $this->currentResourceIndex = $executionContext->getInt(self::STATE_RESOURCE_INDEX);
            $this->currentItemCount = $executionContext->getInt(self::STATE_CURRENT_COUNT);
        }
        $this->openNextResource($executionContext);
    }

    public function update(ExecutionContext $executionContext): void
    {
        $executionContext->put(self::STATE_RESOURCE_INDEX, $this->currentResourceIndex);
        $executionContext->put(self::STATE_CURRENT_COUNT, $this->currentItemCount);
        $this->delegate->update($executionContext);
    }

    public function write(Chunk $items): void
    {
        if (!$this->opened) {
            $this->openNextResource(new ExecutionContext());
        }

        foreach ($items->getOutputItems() as $item) {
            if ($this->currentItemCount >= $this->itemCountLimitPerResource) {
                $this->delegate->close();
                ++$this->currentResourceIndex;
                $this->currentItemCount = 0;
                $this->openNextResource(new ExecutionContext());
            }

            $singleChunk = new Chunk([], [$item]);
            $this->delegate->write($singleChunk);
            ++$this->currentItemCount;
        }
    }

    private function openNextResource(ExecutionContext $executionContext): void
    {
        $this->opened = true;
        // The suffix creator provides the file path for this resource index
        ($this->resourceSuffixCreator)($this->currentResourceIndex);
        $this->delegate->open($executionContext);
    }
}
