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

/**
 * Strategy controlling whether a failing item should be skipped (and recorded) or whether the
 * exception should propagate and fail the step.
 */
interface SkipPolicyInterface
{
    /**
     * @param Throwable $t         exception raised while reading, processing or writing the item
     * @param int       $skipCount number of items already skipped on the same phase of the step
     *
     * @throws SkipLimitExceededException if the configured skip limit is exceeded
     */
    public function shouldSkip(Throwable $t, int $skipCount): bool;
}
