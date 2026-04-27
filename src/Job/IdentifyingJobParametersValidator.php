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
use Lemric\BatchProcessing\Exception\JobParametersInvalidException;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Validates that the identifying portion of incoming {@see JobParameters} matches the one
 * persisted with the {@see \Lemric\BatchProcessing\Domain\JobInstance} of the previous
 * execution.
 */
final readonly class IdentifyingJobParametersValidator implements JobParametersValidatorInterface
{
    public function __construct(
        private string $jobName,
        private JobRepositoryInterface $repository,
    ) {
    }

    public function validate(JobParameters $parameters): void
    {
        $instance = $this->repository->getJobInstanceByJobNameAndParameters($this->jobName, $parameters);
        if (null === $instance) {
            // Brand-new instance — nothing to validate against.
            return;
        }
        $lastExecution = $this->repository->getLastJobExecution($instance);
        if (null === $lastExecution) {
            return;
        }
        $previous = $lastExecution->getJobParameters()->identifyingOnly();
        $incoming = $parameters->identifyingOnly();

        if ($previous->toJobKey() !== $incoming->toJobKey()) {
            throw new JobParametersInvalidException(sprintf('Identifying job parameters for job "%s" do not match the previous execution. Expected key "%s", got "%s".', $this->jobName, $previous->toJobKey(), $incoming->toJobKey()));
        }
    }
}
