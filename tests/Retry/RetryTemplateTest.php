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

use Lemric\BatchProcessing\Retry\Backoff\NoBackOffPolicy;
use Lemric\BatchProcessing\Retry\Policy\{NeverRetryPolicy, SimpleRetryPolicy};
use Lemric\BatchProcessing\Retry\RetryTemplate;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class RetryTemplateTest extends TestCase
{
    public function testNeverRetryPolicyOnlyRunsOnce(): void
    {
        $template = new RetryTemplate(new NeverRetryPolicy(), new NoBackOffPolicy());
        $attempts = 0;
        $this->expectException(RuntimeException::class);
        try {
            $template->execute(static function () use (&$attempts): void {
                ++$attempts;
                throw new RuntimeException('once');
            });
        } finally {
            self::assertSame(1, $attempts);
        }
    }

    public function testNonRetryableExceptionPropagatesImmediately(): void
    {
        $template = new RetryTemplate(
            new SimpleRetryPolicy(maxAttempts: 5, retryableExceptions: [RuntimeException::class => true]),
            new NoBackOffPolicy(),
        );
        $attempts = 0;
        $this->expectException(LogicException::class);
        try {
            $template->execute(static function () use (&$attempts): void {
                ++$attempts;
                throw new LogicException('fatal');
            });
        } finally {
            self::assertSame(1, $attempts);
        }
    }

    public function testRecoveryCallbackIsCalledWhenExhausted(): void
    {
        $template = new RetryTemplate(
            new SimpleRetryPolicy(maxAttempts: 2, retryableExceptions: [RuntimeException::class => true]),
            new NoBackOffPolicy(),
        );

        $value = $template->execute(
            static function (): never {
                throw new RuntimeException('always');
            },
            static fn (Throwable $e, int $attempts): string => 'recovered:'.$attempts,
        );

        self::assertSame('recovered:2', $value);
    }

    public function testRetriesUntilSuccess(): void
    {
        $template = new RetryTemplate(
            new SimpleRetryPolicy(maxAttempts: 5, retryableExceptions: [RuntimeException::class => true]),
            new NoBackOffPolicy(),
        );
        $attempts = 0;
        $value = $template->execute(static function () use (&$attempts): string {
            ++$attempts;
            if ($attempts < 3) {
                throw new RuntimeException('boom');
            }

            return 'eventually';
        });
        self::assertSame('eventually', $value);
        self::assertSame(3, $attempts);
    }

    public function testReturnsValueOnSuccess(): void
    {
        $template = new RetryTemplate();
        $value = $template->execute(static fn () => 'ok');
        self::assertSame('ok', $value);
    }
}
