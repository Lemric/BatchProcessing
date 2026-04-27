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

namespace Lemric\BatchProcessing\Listener\Adapter;

use Lemric\BatchProcessing\Domain\{ExitStatus, StepExecution};
use Lemric\BatchProcessing\Listener\Support\StepExecutionListenerSupport;

final class StepExecutionListenerAdapter extends StepExecutionListenerSupport
{
    use DispatchesHooks;

    public function afterStep(StepExecution $stepExecution): ?ExitStatus
    {
        $this->dispatch('afterStep', $stepExecution);

        return null;
    }

    public function beforeStep(StepExecution $stepExecution): void
    {
        $this->dispatch('beforeStep', $stepExecution);
    }
}
