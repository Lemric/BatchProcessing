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

namespace Lemric\BatchProcessing\Chunk;

/**
 * Controls when a chunk is considered "complete" (full).
 */
interface CompletionPolicyInterface
{
    /**
     * Called after each item is read/processed to check if the chunk is complete.
     */
    public function isComplete(ChunkContext $context, mixed $result = null): bool;

    /**
     * Called at the start of a new chunk.
     */
    public function start(ChunkContext $context): void;

    /**
     * Called after each item is added to the chunk.
     */
    public function update(ChunkContext $context): void;
}
