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

namespace Lemric\BatchProcessing\Partition;

use Lemric\BatchProcessing\Domain\{ExitStatus, StepExecution};

/**
 * Aggregates the ExitStatus values from partition StepExecutions into a single status.
 */
final class StepExecutionAggregator
{
    /**
     * @param list<StepExecution> $executions
     */
    public function aggregate(array $executions): ExitStatus
    {
        $result = ExitStatus::$COMPLETED;
        foreach ($executions as $execution) {
            $result = $result->and($execution->getExitStatus());
        }

        return $result;
    }
}
