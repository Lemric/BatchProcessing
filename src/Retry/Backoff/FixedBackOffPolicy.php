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

/**
 * Sleeps for a fixed period (in milliseconds) between attempts.
 */
final class FixedBackOffPolicy implements BackOffPolicyInterface
{
    /**
     * @param int                        $period  sleep period, in milliseconds
     * @param (callable(int): void)|null $sleeper custom sleeper for tests; receives microseconds
     */
    public function __construct(
        private readonly int $period = 1000,
        private $sleeper = null,
    ) {
    }

    public function backOff(): void
    {
        $micro = max(0, $this->period) * 1000;
        if (null !== $this->sleeper) {
            ($this->sleeper)($micro);

            return;
        }
        usleep($micro);
    }
}
