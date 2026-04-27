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

use Lemric\BatchProcessing\Domain\JobExecution;
use Lemric\BatchProcessing\Listener\Support\JobExecutionListenerSupport;

final class JobExecutionListenerAdapter extends JobExecutionListenerSupport
{
    use DispatchesHooks;

    public function afterJob(JobExecution $jobExecution): void
    {
        $this->dispatch('afterJob', $jobExecution);
    }

    public function beforeJob(JobExecution $jobExecution): void
    {
        $this->dispatch('beforeJob', $jobExecution);
    }
}
