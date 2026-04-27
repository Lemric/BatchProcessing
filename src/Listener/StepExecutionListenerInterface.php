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

namespace Lemric\BatchProcessing\Listener;

use Lemric\BatchProcessing\Domain\{ExitStatus, StepExecution};

interface StepExecutionListenerInterface
{
    /**
     * Allows the listener to influence the final {@see ExitStatus} of the step.
     * Returning {@code null} keeps the step's existing exit status.
     */
    public function afterStep(StepExecution $stepExecution): ?ExitStatus;

    public function beforeStep(StepExecution $stepExecution): void;
}
