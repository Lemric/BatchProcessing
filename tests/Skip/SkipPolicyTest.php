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

use Lemric\BatchProcessing\Exception\SkippableException;
use Lemric\BatchProcessing\Skip\{AlwaysSkipItemSkipPolicy, ExceptionClassifierSkipPolicy, ExceptionHierarchySkipPolicy, NeverSkipItemSkipPolicy};
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SkipPolicyTest extends TestCase
{
    public function testAlwaysSkipAlwaysReturnsTrue(): void
    {
        $policy = new AlwaysSkipItemSkipPolicy();
        self::assertTrue($policy->shouldSkip(new RuntimeException('x'), 0));
        self::assertTrue($policy->shouldSkip(new LogicException('y'), 999));
    }

    public function testExceptionClassifierDispatchesToCorrectPolicy(): void
    {
        $policy = new ExceptionClassifierSkipPolicy(
            policies: [
                RuntimeException::class => new AlwaysSkipItemSkipPolicy(),
            ],
            defaultPolicy: new NeverSkipItemSkipPolicy(),
        );

        self::assertTrue($policy->shouldSkip(new RuntimeException('r'), 0));
        self::assertFalse($policy->shouldSkip(new LogicException('l'), 0));
    }

    public function testExceptionClassifierHonorsSkippableExceptionWhenDefaultIsNeverSkip(): void
    {
        $policy = new ExceptionClassifierSkipPolicy(
            policies: [],
            defaultPolicy: new NeverSkipItemSkipPolicy(),
        );

        self::assertTrue($policy->shouldSkip(new SkippableException('bad row'), 0));
        self::assertFalse($policy->shouldSkip(new RuntimeException('not skippable marker'), 0));
    }

    public function testExceptionClassifierUsesDefaultWhenNoMatch(): void
    {
        $policy = new ExceptionClassifierSkipPolicy(
            policies: [],
            defaultPolicy: new AlwaysSkipItemSkipPolicy(),
        );

        self::assertTrue($policy->shouldSkip(new RuntimeException('x'), 0));
    }

    public function testExceptionHierarchyRespectsExplicitLimitForSkippableException(): void
    {
        $policy = new ExceptionHierarchySkipPolicy([
            SkippableException::class => 1,
        ]);

        self::assertTrue($policy->shouldSkip(new SkippableException('first'), 0));
        self::assertFalse($policy->shouldSkip(new SkippableException('second'), 1));
    }

    public function testExceptionHierarchySkipsUnmappedSkippableException(): void
    {
        $policy = new ExceptionHierarchySkipPolicy([
            RuntimeException::class => 2,
        ]);

        self::assertTrue($policy->shouldSkip(new SkippableException('x'), 0));
        self::assertFalse($policy->shouldSkip(new LogicException('no match'), 0));
    }

    public function testNeverSkipAlwaysReturnsFalse(): void
    {
        $policy = new NeverSkipItemSkipPolicy();
        self::assertFalse($policy->shouldSkip(new RuntimeException('x'), 0));
        self::assertFalse($policy->shouldSkip(new LogicException('y'), 0));
    }
}
