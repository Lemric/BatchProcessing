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

namespace Lemric\BatchProcessing\Job;

use Lemric\BatchProcessing\Domain\{JobExecution, StepExecution};

/**
 * Programmatic decision point used by {@see FlowJob} to determine the next step based on the
 * outcome of the previous one. Returning a specific exit-code string causes the FlowJob to
 * match it against the configured transition map.
 */
interface FlowDeciderInterface
{
    /**
     * Decide the next flow path.
     *
     * @return string An exit-code string that FlowJob matches against its transition map
     */
    public function decide(JobExecution $jobExecution, ?StepExecution $stepExecution): string;
}
