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

namespace Lemric\BatchProcessing\Listener\Support;

use Lemric\BatchProcessing\Domain\{ExitStatus, StepExecution};
use Lemric\BatchProcessing\Listener\StepExecutionListenerInterface;

abstract class StepExecutionListenerSupport implements StepExecutionListenerInterface
{
    public function afterStep(StepExecution $stepExecution): ?ExitStatus
    {
        return null;
    }

    public function beforeStep(StepExecution $stepExecution): void
    {
    }
}
