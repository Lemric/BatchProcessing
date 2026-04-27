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
use Lemric\BatchProcessing\Retry\{RetryContext, RetryPolicyInterface};
use Throwable;

/**
 * Combines several policies. In {@code optimistic} mode (default) the operation can be retried
 * if ANY child policy allows it; in pessimistic mode ALL policies must allow it.
 */
final class CompositeRetryPolicy extends AbstractRetryPolicy
{
    /** @var list<RetryPolicyInterface> */
    private array $policies;

    /**
     * @param iterable<RetryPolicyInterface> $policies
     */
    public function __construct(
        iterable $policies,
        private readonly bool $optimistic = true,
    ) {
        $list = [];
        foreach ($policies as $policy) {
            $list[] = $policy;
        }
        if ([] === $list) {
            throw new InvalidArgumentException('CompositeRetryPolicy requires at least one delegate.');
        }
        $this->policies = $list;
    }

    public function canRetry(RetryContext $context): bool
    {
        if ($this->optimistic) {
            foreach ($this->policies as $p) {
                if ($p->canRetry($context)) {
                    return true;
                }
            }

            return false;
        }
        foreach ($this->policies as $p) {
            if (!$p->canRetry($context)) {
                return false;
            }
        }

        return true;
    }

    public function registerThrowable(RetryContext $context, ?Throwable $throwable): void
    {
        foreach ($this->policies as $p) {
            $p->registerThrowable($context, $throwable);
        }
    }
}
