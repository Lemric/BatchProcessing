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

namespace Lemric\BatchProcessing\Retry\Backoff;

use InvalidArgumentException;

/**
 * Sleeps for a uniformly random duration in the closed interval [min, max] milliseconds.
 */
final class UniformRandomBackOffPolicy implements BackOffPolicyInterface
{
    /**
     * @param (callable(int): void)|null $sleeper custom sleeper for tests
     */
    public function __construct(
        private readonly int $minMs = 500,
        private readonly int $maxMs = 1500,
        private $sleeper = null,
    ) {
        if ($minMs < 0 || $maxMs < $minMs) {
            throw new InvalidArgumentException('Invalid back-off interval.');
        }
    }

    public function backOff(): void
    {
        $sleepMs = random_int($this->minMs, $this->maxMs);
        $micro = $sleepMs * 1000;
        if (null !== $this->sleeper) {
            ($this->sleeper)($micro);

            return;
        }
        usleep($micro);
    }
}
