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

use Lemric\BatchProcessing\Retry\RetryContext;
use Throwable;

/**
 * Builds pre-populated {@see RetryContext} instances for tests covering retry-aware
 * components (interceptors, fault-tolerant chunk processors, custom retry policies).
 */
final class RetryContextMockFactory
{
    public static function exhausted(Throwable $cause): RetryContext
    {
        $context = new RetryContext();
        $context->registerThrowable($cause);
        $context->setExhausted();

        return $context;
    }

    public static function pristine(): RetryContext
    {
        return new RetryContext();
    }

    /**
     * @param array<int, Throwable|class-string<Throwable>> $thrown     sequence of throwables registered as already attempted
     * @param array<string, mixed>                          $attributes
     */
    public static function withAttempts(array $thrown, bool $exhausted = false, array $attributes = []): RetryContext
    {
        $context = new RetryContext();
        foreach ($thrown as $throwable) {
            if (is_string($throwable)) {
                /** @var Throwable $throwable */
                $throwable = new $throwable('mock');
            }
            $context->registerThrowable($throwable);
        }
        if ($exhausted) {
            $context->setExhausted();
        }
        foreach ($attributes as $key => $value) {
            $context->setAttribute($key, $value);
        }

        return $context;
    }
}
