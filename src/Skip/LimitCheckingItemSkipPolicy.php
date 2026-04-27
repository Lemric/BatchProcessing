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

namespace Lemric\BatchProcessing\Skip;

use Lemric\BatchProcessing\Exception\{SkipLimitExceededException, SkippableException};
use Throwable;

/**
 * Skips items whose exception class is mapped to {@code true} in
 * {@code $skippableExceptions}, up to the configured {@code $skipLimit}.
 */
final class LimitCheckingItemSkipPolicy implements SkipPolicyInterface
{
    /**
     * @param array<class-string<Throwable>, bool> $skippableExceptions
     */
    public function __construct(
        private readonly int $skipLimit,
        private readonly array $skippableExceptions = [],
    ) {
    }

    public function shouldSkip(Throwable $t, int $skipCount): bool
    {
        // {@see SkippableException} is an explicit marker — always skippable up to the limit.
        $matched = $t instanceof SkippableException;
        if (!$matched) {
            foreach ($this->skippableExceptions as $class => $skippable) {
                if ($t instanceof $class) {
                    if (!$skippable) {
                        return false;
                    }
                    $matched = true;
                    break;
                }
            }
        }

        if (!$matched) {
            return false;
        }

        if ($skipCount >= $this->skipLimit) {
            throw new SkipLimitExceededException($this->skipLimit, previous: $t);
        }

        return true;
    }
}
