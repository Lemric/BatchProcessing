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
 * Chunk completes after a fixed number of items.
 */
final class SimpleCompletionPolicy implements CompletionPolicyInterface
{
    private int $count = 0;

    public function __construct(
        private readonly int $chunkSize,
    ) {
    }

    public function isComplete(ChunkContext $context, mixed $result = null): bool
    {
        return null === $result || $this->count >= $this->chunkSize;
    }

    public function start(ChunkContext $context): void
    {
        $this->count = 0;
    }

    public function update(ChunkContext $context): void
    {
        ++$this->count;
    }
}
