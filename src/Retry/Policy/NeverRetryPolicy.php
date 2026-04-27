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
 * Never retries; the first failure is propagated to the caller.
 */
final class NeverRetryPolicy extends AbstractRetryPolicy
{
    public function canRetry(RetryContext $context): bool
    {
        return 0 === $context->getRetryCount();
    }
}
