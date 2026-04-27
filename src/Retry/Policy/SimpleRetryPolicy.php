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

namespace Lemric\BatchProcessing\Retry\Policy;

use InvalidArgumentException;
use Lemric\BatchProcessing\Exception\NonTransientException;
use Lemric\BatchProcessing\Retry\RetryContext;
use RuntimeException;
use Throwable;

/**
 * Retries up to {@code $maxAttempts} times for exceptions matching the configured map.
 *
 * The map key is a fully-qualified class name (using {@code instanceof} semantics) and the
 * value is the boolean "retryable" flag. By default only {@see RuntimeException} is retried.
 */
final class SimpleRetryPolicy extends AbstractRetryPolicy
{
    /**
     * @param array<class-string<Throwable>, bool> $retryableExceptions
     */
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly array $retryableExceptions = [RuntimeException::class => true],
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be >= 1');
        }
    }

    public function canRetry(RetryContext $context): bool
    {
        if ($context->getRetryCount() >= $this->maxAttempts) {
            return false;
        }
        $last = $context->getLastThrowable();
        if (null === $last) {
            return true; // initial attempt
        }

        return $this->isRetryable($last);
    }

    private function isRetryable(Throwable $t): bool
    {
        // {@see NonTransientException} is a marker for failures that will not be resolved by
        // retrying — short-circuit regardless of the configured map.
        if ($t instanceof NonTransientException) {
            return false;
        }

        foreach ($this->retryableExceptions as $class => $retryable) {
            if ($t instanceof $class) {
                return $retryable;
            }
        }

        return false;
    }
}
