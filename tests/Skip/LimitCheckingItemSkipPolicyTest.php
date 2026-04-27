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

namespace Lemric\BatchProcessing\Tests\Skip;

use Lemric\BatchProcessing\Exception\SkipLimitExceededException;
use Lemric\BatchProcessing\Skip\LimitCheckingItemSkipPolicy;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LimitCheckingItemSkipPolicyTest extends TestCase
{
    public function testExceptionMarkedNonSkippableShortCircuits(): void
    {
        $policy = new LimitCheckingItemSkipPolicy(10, [LogicException::class => false]);
        self::assertFalse($policy->shouldSkip(new LogicException(), 0));
    }

    public function testNonWhitelistedExceptionIsNotSkipped(): void
    {
        $policy = new LimitCheckingItemSkipPolicy(10, [RuntimeException::class => true]);
        self::assertFalse($policy->shouldSkip(new LogicException(), 0));
    }

    public function testSkipsWhitelistedExceptionsUntilLimit(): void
    {
        $policy = new LimitCheckingItemSkipPolicy(3, [RuntimeException::class => true]);
        self::assertTrue($policy->shouldSkip(new RuntimeException(), 0));
        self::assertTrue($policy->shouldSkip(new RuntimeException(), 2));
    }

    public function testThrowsWhenLimitExceeded(): void
    {
        $this->expectException(SkipLimitExceededException::class);
        $policy = new LimitCheckingItemSkipPolicy(3, [RuntimeException::class => true]);
        $policy->shouldSkip(new RuntimeException(), 3);
    }
}
