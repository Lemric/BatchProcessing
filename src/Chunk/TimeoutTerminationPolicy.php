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
 * Chunk completes after a timeout (in seconds) has elapsed since the chunk started.
 */
final class TimeoutTerminationPolicy implements CompletionPolicyInterface
{
    private float $startTime = 0.0;

    public function __construct(
        private readonly float $timeoutSeconds,
    ) {
    }

    public function isComplete(ChunkContext $context, mixed $result = null): bool
    {
        return null === $result || (microtime(true) - $this->startTime) >= $this->timeoutSeconds;
    }

    public function start(ChunkContext $context): void
    {
        $this->startTime = microtime(true);
    }

    public function update(ChunkContext $context): void
    {
        // no-op
    }
}
