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
 * Delegates processing to an arbitrary method on a POJO.
 *
 * @template TIn
 * @template TOut
 *
 * @implements ItemProcessorInterface<TIn, TOut>
 */
final class ItemProcessorAdapter implements ItemProcessorInterface
{
    public function __construct(
        private readonly object $targetObject,
        private readonly string $targetMethod = 'process',
    ) {
    }

    public function process(mixed $item): mixed
    {
        return $this->targetObject->{$this->targetMethod}($item);
    }
}
