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
 * Retries the operation for up to {@code $timeout} milliseconds measured from the first attempt.
 * Once elapsed the policy refuses further retries.
 */
final class TimeoutRetryPolicy extends AbstractRetryPolicy
{
    private const string ATTR_START = '__timeout_start';

    /**
     * @param int $timeoutMs maximum duration in milliseconds
     */
    public function __construct(private readonly int $timeoutMs = 30_000)
    {
    }

    public function canRetry(RetryContext $context): bool
    {
        $start = $context->getAttribute(self::ATTR_START);
        if (null === $start) {
            return true; // first call, before open()
        }

        /** @var float $start */
        $elapsedMs = (microtime(true) - $start) * 1000;

        return $elapsedMs < $this->timeoutMs;
    }

    public function open(?RetryContext $parent = null): RetryContext
    {
        $context = parent::open($parent);
        $context->setAttribute(self::ATTR_START, microtime(true));

        return $context;
    }
}
