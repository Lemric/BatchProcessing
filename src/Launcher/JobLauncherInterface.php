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

namespace Lemric\BatchProcessing\Launcher;

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};
use Lemric\BatchProcessing\Job\JobInterface;

interface JobLauncherInterface
{
    /**
     * Launches the supplied {@see JobInterface} with the given parameters and returns the
     * resulting {@see JobExecution}.
     */
    public function run(JobInterface $job, JobParameters $parameters): JobExecution;
}
