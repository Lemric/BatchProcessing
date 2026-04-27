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

namespace Lemric\BatchProcessing\Testing;

use Lemric\BatchProcessing\Domain\StepExecution;
use Lemric\BatchProcessing\Skip\SkipCounter;
use Throwable;

/**
 * Convenience factory for tests that exercise the skip framework. Pre-populates a
 * {@see StepExecution}'s {@see ExecutionContext} with a {@see SkipCounter}.
 */
final class SkipContextMockFactory
{
    /**
     * @param array<class-string<Throwable>, int> $perClassCounts
     */
    public static function fresh(array $perClassCounts = []): SkipCounter
    {
        return new SkipCounter($perClassCounts);
    }

    /**
     * @param array<class-string<Throwable>, int> $perClassCounts
     */
    public static function populate(StepExecution $execution, array $perClassCounts): void
    {
        $counter = new SkipCounter($perClassCounts);
        $counter->persistTo($execution->getExecutionContext());
    }
}
