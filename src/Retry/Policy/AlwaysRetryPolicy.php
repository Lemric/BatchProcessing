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
 * Always retries. Use with care - typically combined with another policy via
 * {@see CompositeRetryPolicy}.
 */
final class AlwaysRetryPolicy extends AbstractRetryPolicy
{
    public function canRetry(RetryContext $context): bool
    {
        return true;
    }
}
