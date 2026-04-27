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

namespace Lemric\BatchProcessing\Listener\Support;

use Lemric\BatchProcessing\Listener\RetryListenerInterface;
use Lemric\BatchProcessing\Retry\RetryContext;
use Throwable;

abstract class RetryListenerSupport implements RetryListenerInterface
{
    public function close(RetryContext $context): void
    {
    }

    public function onError(RetryContext $context, Throwable $t): void
    {
    }

    public function open(RetryContext $context): bool
    {
        return true;
    }
}
