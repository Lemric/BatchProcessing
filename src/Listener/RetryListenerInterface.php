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

namespace Lemric\BatchProcessing\Listener;

use Lemric\BatchProcessing\Retry\RetryContext;
use Throwable;

/**
 * Callback interface for retry lifecycle events within {@see \Lemric\BatchProcessing\Retry\RetryTemplate}.
 */
interface RetryListenerInterface
{
    /**
     * Called after the last retry attempt (success or failure).
     */
    public function close(RetryContext $context): void;

    /**
     * Called after each failed attempt.
     */
    public function onError(RetryContext $context, Throwable $t): void;

    /**
     * Called before the first retry attempt. Return false to veto the retry.
     */
    public function open(RetryContext $context): bool;
}
