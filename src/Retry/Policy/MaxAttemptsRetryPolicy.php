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

use Lemric\BatchProcessing\Retry\RetryContext;

/**
 * Simple retry policy based on a maximum number of attempts only.
 *
 * No exception filtering — retries all throwables up to the max.
 */
final class MaxAttemptsRetryPolicy extends AbstractRetryPolicy
{
    public function __construct(
        private readonly int $maxAttempts = 3,
    ) {
    }

    public function canRetry(RetryContext $context): bool
    {
        return $context->getRetryCount() < $this->maxAttempts;
    }
}
