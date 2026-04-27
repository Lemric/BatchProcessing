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
 * Abstraction over the retry execution. Allows decoupling user code from the concrete
 * {@see RetryTemplate} implementation.
 */
interface RetryOperations
{
    /**
     * Executes {@code $callback}, retrying on failure according to the configured policy.
     *
     * @template T
     *
     * @param callable(RetryContext): T          $callback
     * @param (callable(Throwable, int): T)|null $recoveryCallback Optional recovery invoked when retries are exhausted
     *
     * @return T
     */
    public function execute(callable $callback, ?callable $recoveryCallback = null): mixed;
}
