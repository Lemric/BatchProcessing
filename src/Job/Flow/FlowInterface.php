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

namespace Lemric\BatchProcessing\Job\Flow;

use Lemric\BatchProcessing\Domain\JobExecution;

/**
 * Reusable unit of step execution with conditional transitions.
 */
interface FlowInterface
{
    public function getName(): string;

    /**
     * Resume an interrupted flow.
     */
    public function resume(string $stepName, JobExecution $jobExecution): FlowExecutionStatus;

    /**
     * Execute the flow inside the given JobExecution.
     */
    public function start(JobExecution $jobExecution): FlowExecutionStatus;
}
