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
 * Performs no back-off. Suitable for unit tests and operations whose retries are not affected
 * by the polling frequency.
 */
final class NoBackOffPolicy implements BackOffPolicyInterface
{
    public function backOff(): void
    {
    }
}
