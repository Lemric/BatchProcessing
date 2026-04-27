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
use Lemric\BatchProcessing\Item\ItemReaderInterface;

/**
 * Decorator applying a transformation to every item produced by a delegate reader.
 *
 * @template TIn
 * @template TOut
 *
 * @extends AbstractItemReader<TOut>
 */
final class TransformingItemReader extends AbstractItemReader
{
    /**
     * @param ItemReaderInterface<TIn> $delegate
     * @param callable(TIn): TOut      $transformer
     */
    public function __construct(
        private readonly ItemReaderInterface $delegate,
        private $transformer,
        ?string $name = null,
        bool $saveState = false,
    ) {
        parent::__construct($name, $saveState);
    }

    public function close(): void
    {
        if ($this->delegate instanceof \Lemric\BatchProcessing\Item\ItemStreamInterface) {
            $this->delegate->close();
        }
    }

    public function open(ExecutionContext $executionContext): void
    {
        if ($this->delegate instanceof \Lemric\BatchProcessing\Item\ItemStreamInterface) {
            $this->delegate->open($executionContext);
        }
    }

    public function update(ExecutionContext $executionContext): void
    {
        if ($this->delegate instanceof \Lemric\BatchProcessing\Item\ItemStreamInterface) {
            $this->delegate->update($executionContext);
        }
    }

    protected function doRead(): mixed
    {
        $item = $this->delegate->read();
        if (null === $item) {
            return null;
        }

        return ($this->transformer)($item);
    }
}
