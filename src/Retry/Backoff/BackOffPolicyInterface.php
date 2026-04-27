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

namespace Lemric\BatchProcessing\Retry\Backoff;

/**
 * Strategy controlling the wait time between retry attempts.
 */
interface BackOffPolicyInterface
{
    /**
     * Blocks the calling thread (or yields, depending on the implementation) for the configured
     * back-off period before the next retry.
     */
    public function backOff(): void;
}
