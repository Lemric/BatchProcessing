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

namespace Lemric\BatchProcessing\Tests\Retry;

use InvalidArgumentException;
use Lemric\BatchProcessing\Retry\Backoff\{ExponentialBackOffPolicy, FixedBackOffPolicy, NoBackOffPolicy, UniformRandomBackOffPolicy};
use PHPUnit\Framework\TestCase;

final class BackOffPolicyTest extends TestCase
{
    public function testExponentialBackOffPolicyGrowsExponentially(): void
    {
        $slept = [];
        $policy = new ExponentialBackOffPolicy(
            initial: 100,
            multiplier: 2.0,
            max: 1000,
            sleeper: static function (int $micro) use (&$slept): void {
                $slept[] = $micro;
            },
        );
        $policy->backOff(); // 100ms
        $policy->backOff(); // 200ms
        $policy->backOff(); // 400ms
        $policy->backOff(); // 800ms
        $policy->backOff(); // capped at 1000ms

        self::assertSame([100_000, 200_000, 400_000, 800_000, 1_000_000], $slept);
    }

    public function testExponentialBackOffPolicyRejectsMultiplierLessThanOrEqualToOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExponentialBackOffPolicy(multiplier: 1.0);
    }

    public function testExponentialBackOffPolicyResetToInitial(): void
    {
        $slept = [];
        $policy = new ExponentialBackOffPolicy(
            initial: 50,
            multiplier: 3.0,
            max: 10_000,
            sleeper: static function (int $micro) use (&$slept): void {
                $slept[] = $micro;
            },
        );
        $policy->backOff(); // 50
        $policy->backOff(); // 150
        $policy->reset();
        $policy->backOff(); // back to 50
        self::assertSame([50_000, 150_000, 50_000], $slept);
    }

    public function testFixedBackOffPolicyNegativePeriodClampedToZero(): void
    {
        $slept = [];
        $policy = new FixedBackOffPolicy(period: -10, sleeper: static function (int $micro) use (&$slept): void {
            $slept[] = $micro;
        });
        $policy->backOff();
        self::assertSame([0], $slept);
    }

    public function testFixedBackOffPolicySleepsForConfiguredPeriod(): void
    {
        $slept = [];
        $policy = new FixedBackOffPolicy(period: 200, sleeper: static function (int $micro) use (&$slept): void {
            $slept[] = $micro;
        });
        $policy->backOff();
        $policy->backOff();
        self::assertSame([200_000, 200_000], $slept);
    }

    public function testNoBackOffPolicyDoesNotSleep(): void
    {
        $policy = new NoBackOffPolicy();
        $start = hrtime(true);
        $policy->backOff();
        $elapsed = (hrtime(true) - $start) / 1e6; // ms
        self::assertLessThan(50, $elapsed);
    }

    public function testUniformRandomBackOffPolicyRejectsInvalidRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UniformRandomBackOffPolicy(minMs: 500, maxMs: 100);
    }

    public function testUniformRandomBackOffPolicyRejectsNegativeMin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UniformRandomBackOffPolicy(minMs: -1, maxMs: 100);
    }

    public function testUniformRandomBackOffPolicySleepsWithinRange(): void
    {
        $slept = [];
        $policy = new UniformRandomBackOffPolicy(
            minMs: 100,
            maxMs: 200,
            sleeper: static function (int $micro) use (&$slept): void {
                $slept[] = $micro;
            },
        );
        for ($i = 0; $i < 20; ++$i) {
            $policy->backOff();
        }
        foreach ($slept as $micro) {
            self::assertGreaterThanOrEqual(100_000, $micro);
            self::assertLessThanOrEqual(200_000, $micro);
        }
    }
}
