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

use const PHP_INT_MAX;

/**
 * Convenience base class providing optional state-saving and a hook-based API for subclasses.
 *
 * Subclasses implement {@see doRead()}; the base class will increment the read counter,
 * optionally persist the count into the {@see ExecutionContext} (when {@code saveState=true})
 * and skip already-read items on restart.
 *
 * @template TItem
 *
 * @implements ItemReaderInterface<TItem>
 */
abstract class AbstractItemReader implements ItemReaderInterface, ItemStreamInterface
{
    protected int $currentItemCount = 0;

    protected int $maxItemCount = PHP_INT_MAX;

    protected string $name;

    protected bool $saveState = true;

    public function __construct(?string $name = null, bool $saveState = true)
    {
        $this->name = $name ?? static::class;
        $this->saveState = $saveState;
    }

    public function close(): void
    {
        $this->currentItemCount = 0;
        $this->doClose();
    }

    public function open(ExecutionContext $executionContext): void
    {
        if ($executionContext->containsKey($this->stateKey('read.count'))) {
            $this->currentItemCount = $executionContext->getInt($this->stateKey('read.count'));
            $this->doJumpToItem($this->currentItemCount);
        } else {
            $this->doOpen();
        }
    }

    public function read(): mixed
    {
        if ($this->currentItemCount >= $this->maxItemCount) {
            return null;
        }
        $item = $this->doRead();
        if (null === $item) {
            return null;
        }
        ++$this->currentItemCount;

        return $item;
    }

    public function setMaxItemCount(int $count): void
    {
        $this->maxItemCount = $count;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setSaveState(bool $saveState): void
    {
        $this->saveState = $saveState;
    }

    public function update(ExecutionContext $executionContext): void
    {
        if ($this->saveState) {
            $executionContext->put($this->stateKey('read.count'), $this->currentItemCount);
        }
    }

    protected function doClose(): void
    {
    }

    /**
     * Skips $count items so that the next read returns the item AFTER the last one previously read.
     *
     * Subclasses with cheap random access can override this for efficiency.
     */
    protected function doJumpToItem(int $count): void
    {
        $this->doOpen();
        for ($i = 0; $i < $count; ++$i) {
            $this->doRead();
        }
    }

    protected function doOpen(): void
    {
    }

    /**
     * @return TItem|null
     */
    abstract protected function doRead(): mixed;

    protected function stateKey(string $suffix): string
    {
        return $this->name.'.'.$suffix;
    }
}
