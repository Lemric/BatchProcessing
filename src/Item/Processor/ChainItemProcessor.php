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

use Lemric\BatchProcessing\Item\ItemProcessorInterface;

/**
 * Alias / short-form of {@see CompositeItemProcessor} for chaining filtering processors.
 *
 * @template TIn
 * @template TOut
 *
 * @implements ItemProcessorInterface<TIn, TOut>
 */
final class ChainItemProcessor implements ItemProcessorInterface
{
    /** @var list<ItemProcessorInterface<mixed, mixed>> */
    private readonly array $processors;

    /**
     * @param list<ItemProcessorInterface<mixed, mixed>> $processors
     */
    public function __construct(array $processors)
    {
        $this->processors = $processors;
    }

    public function process(mixed $item): mixed
    {
        $result = $item;
        foreach ($this->processors as $processor) {
            $result = $processor->process($result);
            if (null === $result) {
                return null;
            }
        }

        return $result;
    }
}
