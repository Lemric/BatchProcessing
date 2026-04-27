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

/**
 * Marker interface combining {@see ItemWriterInterface} and {@see ItemStreamInterface}
 * with awareness of the underlying resource (e.g. file path).
 *
 * @template T
 *
 * @extends ItemWriterInterface<T>
 */
interface ResourceAwareItemWriterItemStreamInterface extends ItemWriterInterface, ItemStreamInterface
{
    public function setResource(string $resource): void;
}
