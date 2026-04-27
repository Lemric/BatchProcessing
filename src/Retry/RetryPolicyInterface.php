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

namespace Lemric\BatchProcessing\Retry;

use Throwable;

/**
 * Strategy controlling whether a failed operation should be retried. Implementations are
 * stateless - all per-call state lives in the supplied {@see RetryContext}.
 */
interface RetryPolicyInterface
{
    /**
     * @return bool {@code true} if the framework should retry the operation, {@code false} otherwise
     */
    public function canRetry(RetryContext $context): bool;

    public function close(RetryContext $context): void;

    public function open(?RetryContext $parent = null): RetryContext;

    public function registerThrowable(RetryContext $context, ?Throwable $throwable): void;
}
