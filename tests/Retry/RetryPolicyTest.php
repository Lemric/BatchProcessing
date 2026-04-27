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
use Lemric\BatchProcessing\Retry\Policy\{AlwaysRetryPolicy, CompositeRetryPolicy, ExceptionClassifierRetryPolicy, NeverRetryPolicy, SimpleRetryPolicy};
use Lemric\BatchProcessing\Retry\RetryContext;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RetryPolicyTest extends TestCase
{
    public function testAlwaysRetryPolicyAlwaysAllows(): void
    {
        $policy = new AlwaysRetryPolicy();
        $ctx = $policy->open();
        self::assertTrue($policy->canRetry($ctx));
        $policy->registerThrowable($ctx, new RuntimeException('fail'));
        self::assertTrue($policy->canRetry($ctx));
        $policy->registerThrowable($ctx, new RuntimeException('fail again'));
        self::assertTrue($policy->canRetry($ctx));
        $policy->close($ctx);
    }

    public function testCompositeRetryPolicyOptimisticMode(): void
    {
        $always = new AlwaysRetryPolicy();
        $never = new NeverRetryPolicy();

        // optimistic: any policy can allow
        $policy = new CompositeRetryPolicy([$always, $never], optimistic: true);
        $ctx = $policy->open();
        $policy->registerThrowable($ctx, new RuntimeException('boom'));
        self::assertTrue($policy->canRetry($ctx)); // AlwaysRetryPolicy says yes
    }

    public function testCompositeRetryPolicyPessimisticMode(): void
    {
        $always = new AlwaysRetryPolicy();
        $never = new NeverRetryPolicy();

        // pessimistic: ALL must allow
        $policy = new CompositeRetryPolicy([$always, $never], optimistic: false);
        $ctx = $policy->open();
        $policy->registerThrowable($ctx, new RuntimeException('boom'));
        self::assertFalse($policy->canRetry($ctx)); // NeverRetryPolicy says no
    }

    public function testCompositeRetryPolicyRejectsEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CompositeRetryPolicy([]);
    }

    public function testExceptionClassifierRetryPolicyDispatchesCorrectly(): void
    {
        $policy = new ExceptionClassifierRetryPolicy(
            policies: [
                RuntimeException::class => new SimpleRetryPolicy(maxAttempts: 3, retryableExceptions: [RuntimeException::class => true]),
            ],
            defaultPolicy: new NeverRetryPolicy(),
        );
        $ctx = $policy->open();

        // RuntimeException → SimpleRetryPolicy → eligible
        $policy->registerThrowable($ctx, new RuntimeException('r'));
        self::assertTrue($policy->canRetry($ctx));

        // LogicException → NeverRetryPolicy
        $policy->registerThrowable($ctx, new LogicException('l'));
        self::assertFalse($policy->canRetry($ctx));
    }

    public function testNeverRetryPolicyAllowsFirstAttemptOnly(): void
    {
        $policy = new NeverRetryPolicy();
        $ctx = $policy->open();
        self::assertTrue($policy->canRetry($ctx));
        $policy->registerThrowable($ctx, new RuntimeException('fail'));
        self::assertFalse($policy->canRetry($ctx));
        $policy->close($ctx);
    }

    public function testRetryContextTracksState(): void
    {
        $ctx = new RetryContext();
        self::assertSame(0, $ctx->getRetryCount());
        self::assertNull($ctx->getLastThrowable());
        self::assertFalse($ctx->isExhausted());

        $ex = new RuntimeException('fail');
        $ctx->registerThrowable($ex);
        self::assertSame(1, $ctx->getRetryCount());
        self::assertSame($ex, $ctx->getLastThrowable());

        $ctx->setExhausted();
        self::assertTrue($ctx->isExhausted());
    }

    public function testSimpleRetryPolicyRejectsNonRetryableException(): void
    {
        $policy = new SimpleRetryPolicy(maxAttempts: 5, retryableExceptions: [RuntimeException::class => true]);
        $ctx = $policy->open();
        $policy->registerThrowable($ctx, new LogicException('fatal'));
        self::assertFalse($policy->canRetry($ctx));
    }

    public function testSimpleRetryPolicyRespectsMaxAttempts(): void
    {
        $policy = new SimpleRetryPolicy(maxAttempts: 3, retryableExceptions: [RuntimeException::class => true]);
        $ctx = $policy->open();
        self::assertTrue($policy->canRetry($ctx));

        $policy->registerThrowable($ctx, new RuntimeException('1'));
        self::assertTrue($policy->canRetry($ctx));

        $policy->registerThrowable($ctx, new RuntimeException('2'));
        self::assertTrue($policy->canRetry($ctx));

        $policy->registerThrowable($ctx, new RuntimeException('3'));
        self::assertFalse($policy->canRetry($ctx));
    }
}
