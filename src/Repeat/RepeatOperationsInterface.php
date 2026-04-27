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

namespace Lemric\BatchProcessing\Repeat;

use Lemric\BatchProcessing\Step\RepeatStatus;

/**
 * Repeat operations contract — orthogonal to retry.
 */
interface RepeatOperationsInterface
{
    /**
     * Iterates the callback until it returns {@see RepeatStatus::FINISHED}
     * or the completion policy is satisfied.
     *
     * @param callable(): RepeatStatus $callback
     */
    public function iterate(callable $callback): RepeatStatus;
}
