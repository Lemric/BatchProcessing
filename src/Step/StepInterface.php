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

namespace Lemric\BatchProcessing\Step;

use Lemric\BatchProcessing\Domain\{StepExecution};

/**
 * The fundamental unit of execution within a Job. Implementations are expected to be safe to
 * invoke from multiple JobExecutions concurrently (i.e. carry no per-run state of their own).
 */
interface StepInterface
{
    /**
     * Executes the step against the provided {@see StepExecution}. Implementations MUST update
     * the step status, exit status and any relevant counters on the StepExecution.
     */
    public function execute(StepExecution $stepExecution): void;

    public function getName(): string;

    /**
     * Returns the maximum number of times this step is allowed to start within the same
     * {@see JobInstance} (across restarts).
     */
    public function getStartLimit(): int;

    /**
     * Whether the framework should retry this step from scratch when the previous execution
     * was unsuccessful.
     */
    public function isAllowStartIfComplete(): bool;
}
