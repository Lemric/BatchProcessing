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

/**
 * Dispatches to a different policy depending on the exception type.
 *
 * The {@code $classifier} maps a {@see Throwable} to one of the {@code $policies}. If no
 * policy matches the {@code $defaultPolicy} is used.
 */
final class ExceptionClassifierRetryPolicy extends AbstractRetryPolicy
{
    /**
     * @param array<class-string<Throwable>, RetryPolicyInterface> $policies
     */
    public function __construct(
        private readonly array $policies,
        private readonly RetryPolicyInterface $defaultPolicy = new NeverRetryPolicy(),
    ) {
    }

    public function canRetry(RetryContext $context): bool
    {
        return $this->policyFor($context->getLastThrowable())->canRetry($context);
    }

    public function registerThrowable(RetryContext $context, ?Throwable $throwable): void
    {
        $this->policyFor($throwable)->registerThrowable($context, $throwable);
    }

    private function policyFor(?Throwable $t): RetryPolicyInterface
    {
        if (null === $t) {
            return $this->defaultPolicy;
        }
        foreach ($this->policies as $class => $policy) {
            if ($t instanceof $class) {
                return $policy;
            }
        }

        return $this->defaultPolicy;
    }
}
