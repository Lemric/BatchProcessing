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
 * Exponentially increasing back-off period: {@code period × multiplier^attempt}, capped at
 * {@code maxInterval}.
 */
final class ExponentialBackOffPolicy implements BackOffPolicyInterface
{
    private float $current;

    /**
     * @param int                        $initial    initial sleep period in milliseconds
     * @param float                      $multiplier Multiplier applied after every back-off (must be > 1.0).
     * @param int                        $max        upper bound on the sleep period in milliseconds
     * @param (callable(int): void)|null $sleeper    custom sleeper for tests
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
        $sleepMs = (int) min($this->current, $this->max);
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
