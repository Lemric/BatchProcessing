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

use Fiber;
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemStreamInterface};

/**
 * Thread-safe decorator that synchronizes {@see read()} calls on the wrapped reader
 * using a simple mutex pattern. Required for multi-threaded step execution.
 *
 * @template TItem
 *
 * @implements ItemReaderInterface<TItem>
 */
final class SynchronizedItemStreamReader implements ItemReaderInterface, ItemStreamInterface
{
    private bool $locked = false;

    /**
     * @param ItemReaderInterface<TItem> $delegate
     */
    public function __construct(
        private readonly ItemReaderInterface $delegate,
    ) {
    }

    public function close(): void
    {
        if ($this->delegate instanceof ItemStreamInterface) {
            $this->delegate->close();
        }
    }

    public function open(ExecutionContext $executionContext): void
    {
        if ($this->delegate instanceof ItemStreamInterface) {
            $this->delegate->open($executionContext);
        }
    }

    public function read(): mixed
    {
        while ($this->locked) {
            // Spin-wait in cooperative multitasking (Fiber) context
            if (class_exists(Fiber::class) && null !== Fiber::getCurrent()) {
                Fiber::suspend();
            }
        }

        $this->locked = true;
        try {
            return $this->delegate->read();
        } finally {
            $this->locked = false;
        }
    }

    public function update(ExecutionContext $executionContext): void
    {
        if ($this->delegate instanceof ItemStreamInterface) {
            $this->delegate->update($executionContext);
        }
    }
}
