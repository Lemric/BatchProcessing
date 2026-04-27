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

use InvalidArgumentException;
use Lemric\BatchProcessing\Item\ItemProcessorInterface;

/**
 * Chain of {@see ItemProcessorInterface} instances. Each processor is applied to the output of
 * the previous one. As soon as a processor returns {@code null} the item is filtered out and
 * the rest of the chain is skipped.
 *
 * @template TIn
 * @template TOut
 *
 * @implements ItemProcessorInterface<TIn, TOut>
 */
final class CompositeItemProcessor implements ItemProcessorInterface
{
    /** @var list<ItemProcessorInterface<mixed, mixed>> */
    private array $delegates;

    /**
     * @param iterable<ItemProcessorInterface<mixed, mixed>> $delegates
     */
    public function __construct(iterable $delegates)
    {
        $list = [];
        foreach ($delegates as $delegate) {
            $list[] = $delegate;
        }
        if ([] === $list) {
            throw new InvalidArgumentException('CompositeItemProcessor requires at least one delegate.');
        }
        $this->delegates = $list;
    }

    public function process(mixed $item): mixed
    {
        $current = $item;
        foreach ($this->delegates as $delegate) {
            $current = $delegate->process($current);
            if (null === $current) {
                return null;
            }
        }

        return $current;
    }
}
