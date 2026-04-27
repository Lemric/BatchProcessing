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
 * Exponential back-off with random jitter ("decorrelated jitter"). Prevents the thundering-herd
 * problem by randomising the sleep period within the exponentially growing window.
 *
 * Formula: {@code sleep = random(initial, min(max, current * multiplier))}
 */
final class ExponentialRandomBackOffPolicy implements BackOffPolicyInterface
{
    private float $current;

    /**
     * @param int                        $initial    initial sleep period in milliseconds
     * @param float                      $multiplier growth factor (must be > 1.0)
     * @param int                        $max        upper bound on the sleep period in milliseconds
     * @param (callable(int): void)|null $sleeper    custom sleeper for tests (receives microseconds)
     */
    public function __construct(
        private readonly int $initial = 100,
        private readonly float $multiplier = 2.0,
        private readonly int $max = 30_000,
        private $sleeper = null,
    ) {
        if ($multiplier <= 1.0) {
            throw new InvalidArgumentException('Multiplier must be > 1.0 to grow exponentially.');
        }
        $this->current = (float) $initial;
    }

    public function backOff(): void
    {
        $ceiling = (int) min($this->current, $this->max);
        $sleepMs = random_int($this->initial, max($this->initial, $ceiling));
        $micro = max(0, $sleepMs) * 1000;

        if (null !== $this->sleeper) {
            ($this->sleeper)($micro);
        } else {
            usleep($micro);
        }

        $this->current = min($this->current * $this->multiplier, (float) $this->max);
    }

    public function reset(): void
    {
        $this->current = (float) $this->initial;
    }
}
