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
 * Strategy that transforms or filters a single item between read and write.
 *
 * Returning {@code null} signals that the item should be filtered out (it will not be passed
 * to the writer and the {@code filterCount} of the step will be incremented).
 *
 * @template TInput
 *
 * @template-covariant TOutput
 */
interface ItemProcessorInterface
{
    /**
     * @param TInput $item
     *
     * @return TOutput|null {@code null} = filter the item out
     */
    public function process(mixed $item): mixed;
}
