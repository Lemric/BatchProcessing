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

use Lemric\BatchProcessing\Exception\SkipLimitExceededException;
use Throwable;

use const PHP_INT_MAX;

/**
 * Always skip up to the supplied limit, regardless of the exception type.
 */
final class AlwaysSkipItemSkipPolicy implements SkipPolicyInterface
{
    public function __construct(private readonly int $skipLimit = PHP_INT_MAX)
    {
    }

    public function shouldSkip(Throwable $t, int $skipCount): bool
    {
        if ($skipCount >= $this->skipLimit) {
            throw new SkipLimitExceededException($this->skipLimit, previous: $t);
        }

        return true;
    }
}
