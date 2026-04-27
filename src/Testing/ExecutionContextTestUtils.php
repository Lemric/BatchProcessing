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

namespace Lemric\BatchProcessing\Testing;

use Lemric\BatchProcessing\Domain\ExecutionContext;
use PHPUnit\Framework\Assert;

/**
 * Assertions on ExecutionContext contents for testing.
 */
final class ExecutionContextTestUtils
{
    public static function assertContainsKey(ExecutionContext $ctx, string $key, string $message = ''): void
    {
        Assert::assertTrue($ctx->containsKey($key), $message ?: "ExecutionContext missing key: {$key}");
    }

    public static function assertDirty(ExecutionContext $ctx, string $message = ''): void
    {
        Assert::assertTrue($ctx->isDirty(), $message ?: 'ExecutionContext should be dirty');
    }

    public static function assertKeyEquals(ExecutionContext $ctx, string $key, mixed $expected, string $message = ''): void
    {
        self::assertContainsKey($ctx, $key, $message);
        Assert::assertEquals($expected, $ctx->get($key), $message ?: "ExecutionContext key '{$key}' value mismatch");
    }

    public static function assertNotContainsKey(ExecutionContext $ctx, string $key, string $message = ''): void
    {
        Assert::assertFalse($ctx->containsKey($key), $message ?: "ExecutionContext should not contain key: {$key}");
    }

    public static function assertNotDirty(ExecutionContext $ctx, string $message = ''): void
    {
        Assert::assertFalse($ctx->isDirty(), $message ?: 'ExecutionContext should not be dirty');
    }
}
