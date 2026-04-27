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

use Lemric\BatchProcessing\Domain\JobExecution;
use Lemric\BatchProcessing\Listener\JobExecutionListenerInterface;

abstract class JobExecutionListenerSupport implements JobExecutionListenerInterface
{
    public function afterJob(JobExecution $jobExecution): void
    {
    }

    public function beforeJob(JobExecution $jobExecution): void
    {
    }
}
