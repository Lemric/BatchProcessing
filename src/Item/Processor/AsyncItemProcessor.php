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

namespace Lemric\BatchProcessing\Item\Processor;

use Fiber;
use Lemric\BatchProcessing\Item\ItemProcessorInterface;

/**
 * Wraps a delegate processor — each process() call is started in a Fiber, returning
 * a {@see Fiber} that the matching {@see AsyncItemWriter} will resolve before writing.
 *
 * @template TIn
 * @template TOut
 *
 * @implements ItemProcessorInterface<TIn, mixed>
 */
final class AsyncItemProcessor implements ItemProcessorInterface
{
    /**
     * @param ItemProcessorInterface<TIn, TOut> $delegate
     */
    public function __construct(
        private readonly ItemProcessorInterface $delegate,
    ) {
    }

    /**
     * @param TIn $item
     */
    public function process(mixed $item): mixed
    {
        $delegate = $this->delegate;
        $fiber = new Fiber(static function () use ($delegate, $item): mixed {
            return $delegate->process($item);
        });
        $fiber->start();

        return $fiber;
    }
}
