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
use Lemric\BatchProcessing\Item\ItemWriterInterface;

/**
 * Adapter wrapping any service method as an {@see ItemWriterInterface}.
 *
 * @template TItem
 *
 * @implements ItemWriterInterface<TItem>
 */
final class ItemWriterAdapter implements ItemWriterInterface
{
    /**
     * @param object $targetObject the service containing the method
     * @param string $targetMethod the method name to invoke (receives the Chunk)
     */
    public function __construct(
        private readonly object $targetObject,
        private readonly string $targetMethod,
    ) {
    }

    public function write(Chunk $items): void
    {
        $this->targetObject->{$this->targetMethod}($items);
    }
}
