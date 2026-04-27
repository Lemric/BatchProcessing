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

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};

interface JobInterface
{
    /**
     * Runs the job using the supplied {@see JobExecution}. Implementations must update the
     * status / exit status on the JobExecution and persist them via the {@see JobRepositoryInterface}.
     */
    public function execute(JobExecution $jobExecution): void;

    public function getName(): string;

    /**
     * Whether this job can be re-launched after a failed/stopped execution.
     */
    public function isRestartable(): bool;

    /**
     * Validates the supplied parameters before launching. Throws on invalid input.
     */
    public function validateParameters(JobParameters $parameters): void;
}
