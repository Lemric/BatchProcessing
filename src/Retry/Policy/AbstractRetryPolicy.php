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
 * Convenience base class implementing {@see open()}, {@see close()} and {@see registerThrowable()}.
 */
abstract class AbstractRetryPolicy implements RetryPolicyInterface
{
    public function close(RetryContext $context): void
    {
    }

    public function open(?RetryContext $parent = null): RetryContext
    {
        return new RetryContext($parent);
    }

    public function registerThrowable(RetryContext $context, ?Throwable $throwable): void
    {
        $context->registerThrowable($throwable);
    }
}
