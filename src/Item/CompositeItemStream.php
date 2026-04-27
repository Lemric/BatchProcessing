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

namespace Lemric\BatchProcessing\Item;

use Lemric\BatchProcessing\Domain\ExecutionContext;
use Throwable;

use function in_array;

/**
 * Composite {@see ItemStreamInterface} aggregating multiple delegate streams. open()/update()/close()
 * are dispatched to every registered stream. close() is best-effort and continues on failure.
 */
final class CompositeItemStream implements ItemStreamInterface
{
    /** @var list<ItemStreamInterface> */
    private array $streams = [];

    public function close(): void
    {
        $first = null;
        foreach ($this->streams as $stream) {
            try {
                $stream->close();
            } catch (Throwable $t) {
                $first ??= $t;
            }
        }
        if (null !== $first) {
            throw $first;
        }
    }

    public function open(ExecutionContext $executionContext): void
    {
        foreach ($this->streams as $stream) {
            $stream->open($executionContext);
        }
    }

    public function register(ItemStreamInterface $stream): void
    {
        if (!in_array($stream, $this->streams, true)) {
            $this->streams[] = $stream;
        }
    }

    public function update(ExecutionContext $executionContext): void
    {
        foreach ($this->streams as $stream) {
            $stream->update($executionContext);
        }
    }
}
