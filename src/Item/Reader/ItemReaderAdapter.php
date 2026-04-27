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

use Lemric\BatchProcessing\Item\ItemReaderInterface;

/**
 * Adapter wrapping any service method as an {@see ItemReaderInterface}.
 * Calls the target method on the target object on each {@see read()}.
 * Returns null when the method returns null (end of data).
 *
 * @template TItem
 *
 * @implements ItemReaderInterface<TItem>
 */
final class ItemReaderAdapter implements ItemReaderInterface
{
    /**
     * @param object $targetObject the service containing the method
     * @param string $targetMethod the method name to invoke
     */
    public function __construct(
        private readonly object $targetObject,
        private readonly string $targetMethod,
    ) {
    }

    public function read(): mixed
    {
        return $this->targetObject->{$this->targetMethod}();
    }
}
