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

/**
 * Formal callback contract for operations executed within a {@see RetryTemplate}.
 * Provides a typed alternative to anonymous closures.
 *
 * @template T
 */
interface RetryCallback
{
    /**
     * Executes the retryable operation.
     *
     * @return T
     */
    public function doWithRetry(RetryContext $context): mixed;
}
