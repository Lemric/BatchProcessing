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

use Lemric\BatchProcessing\Retry\{RetryContext, RetryPolicyInterface};
use Throwable;

use function is_finite;
use function is_float;
use function is_int;
use function is_string;

/**
 * Circuit Breaker retry policy.
 *
 * After a configurable number of failures within a time window the circuit "opens" and
 * all retry attempts are rejected immediately. After a reset timeout the circuit
 * "half-opens" and a single attempt is allowed.
 */
final class CircuitBreakerRetryPolicy implements RetryPolicyInterface
{
    private const string ATTR_CIRCUIT_OPEN = 'circuit.open';

    private const string ATTR_CIRCUIT_OPEN_TIME = 'circuit.openTime';

    private const string ATTR_FAILURE_COUNT = 'circuit.failures';

    public function __construct(
        private readonly RetryPolicyInterface $delegate,
        private readonly int $openTimeout = 5000, // ms
        private readonly int $resetTimeout = 20000, // ms
    ) {
    }

    public function canRetry(RetryContext $context): bool
    {
        if ($context->getAttribute(self::ATTR_CIRCUIT_OPEN, false)) {
            $openTime = self::coerceIntAttribute($context, self::ATTR_CIRCUIT_OPEN_TIME, 0);
            $elapsed = $this->currentTimeMs() - $openTime;

            if ($elapsed < $this->resetTimeout) {
                return false; // circuit is open, reject
            }

            // Half-open: allow one attempt
            return true;
        }

        return $this->delegate->canRetry($context);
    }

    public function close(RetryContext $context): void
    {
        $this->delegate->close($context);
    }

    public function open(?RetryContext $parent = null): RetryContext
    {
        $context = $this->delegate->open($parent);
        $context->setAttribute(self::ATTR_CIRCUIT_OPEN, false);
        $context->setAttribute(self::ATTR_FAILURE_COUNT, 0);

        return $context;
    }

    public function registerThrowable(RetryContext $context, ?Throwable $throwable): void
    {
        $this->delegate->registerThrowable($context, $throwable);

        if (null !== $throwable) {
            $failures = self::coerceIntAttribute($context, self::ATTR_FAILURE_COUNT, 0) + 1;
            $context->setAttribute(self::ATTR_FAILURE_COUNT, $failures);

            if (!$context->getAttribute(self::ATTR_CIRCUIT_OPEN, false)) {
                $openAfter = (int) ceil($this->openTimeout / 1000);
                if ($failures >= $openAfter) {
                    $context->setAttribute(self::ATTR_CIRCUIT_OPEN, true);
                    $context->setAttribute(self::ATTR_CIRCUIT_OPEN_TIME, $this->currentTimeMs());
                }
            }
        } else {
            // Success resets
            $context->setAttribute(self::ATTR_CIRCUIT_OPEN, false);
            $context->setAttribute(self::ATTR_FAILURE_COUNT, 0);
        }
    }

    private static function coerceIntAttribute(RetryContext $context, string $key, int $default): int
    {
        $value = $context->getAttribute($key, $default);
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && is_finite($value)) {
            return (int) round($value);
        }
        if (is_string($value) && 1 === preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        return $default;
    }

    private function currentTimeMs(): int
    {
        return (int) (microtime(true) * 1000);
    }
}
