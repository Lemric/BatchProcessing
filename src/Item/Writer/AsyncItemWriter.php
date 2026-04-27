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

use Fiber;
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;

/**
 * Resolves Fiber results from {@see AsyncItemProcessor} and delegates actual writing
 * to the wrapped writer.
 *
 * @implements ItemWriterInterface<mixed>
 */
final class AsyncItemWriter implements ItemWriterInterface
{
    /**
     * @param ItemWriterInterface<mixed> $delegate
     */
    public function __construct(
        private readonly ItemWriterInterface $delegate,
    ) {
    }

    public function write(Chunk $items): void
    {
        $resolved = [];
        foreach ($items->getOutputItems() as $fiber) {
            if ($fiber instanceof Fiber) {
                while (!$fiber->isTerminated()) {
                    if ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                }
                $value = $fiber->getReturn();
            } else {
                $value = $fiber;
            }
            if (null !== $value) {
                $resolved[] = $value;
            }
        }

        if ([] !== $resolved) {
            $this->delegate->write(new Chunk($resolved, $resolved));
        }
    }
}
