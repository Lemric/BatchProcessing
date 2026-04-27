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

use Lemric\BatchProcessing\Domain\JobParameters;

/**
 * Extracts {@see JobParameters} to be used when launching a child Job from a {@see JobStep}.
 */
interface JobParametersExtractorInterface
{
    public function getJobParameters(JobInterface $job, \Lemric\BatchProcessing\Domain\StepExecution $stepExecution): JobParameters;
}
