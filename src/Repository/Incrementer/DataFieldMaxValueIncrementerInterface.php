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

namespace Lemric\BatchProcessing\Repository\Incrementer;

/**
 * Per-platform monotonic id allocator.
 * Used as a fallback when {@code lastInsertId()} cannot be relied upon (e.g. multi-master
 * clusters, batched inserts via prepared statements).
 */
interface DataFieldMaxValueIncrementerInterface
{
    /**
     * Returns the next monotonic id for the configured sequence/table.
     */
    public function nextLongValue(): int;
}
