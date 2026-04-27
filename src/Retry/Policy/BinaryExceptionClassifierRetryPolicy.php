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

use Lemric\BatchProcessing\Classifier\BinaryExceptionClassifier;
use Lemric\BatchProcessing\Retry\RetryContext;

/**
 * Retry policy that uses a BinaryExceptionClassifier to decide which exceptions are retryable.
 */
final class BinaryExceptionClassifierRetryPolicy extends AbstractRetryPolicy
{
    public function __construct(
        private readonly BinaryExceptionClassifier $classifier,
        private readonly int $maxAttempts = 3,
    ) {
    }

    public function canRetry(RetryContext $context): bool
    {
        if ($context->getRetryCount() >= $this->maxAttempts) {
            return false;
        }

        $last = $context->getLastThrowable();
        if (null === $last) {
            return true;
        }

        return $this->classifier->classify($last);
    }
}
