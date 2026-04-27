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
use Lemric\BatchProcessing\Exception\JobInterruptedException;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Strategy consulted between steps to detect cooperative interruption (SIGINT/SIGTERM,
 * external STOP request persisted in the repository, custom flag).
 */
interface JobInterruptionPolicyInterface
{
    /**
     * @throws JobInterruptedException when execution should stop
     */
    public function checkInterrupted(JobExecution $jobExecution, JobRepositoryInterface $repository, JobParameters $parameters): void;
}
