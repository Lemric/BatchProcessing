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

namespace Lemric\BatchProcessing\Testing;

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters, StepExecution};
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Step\StepInterface;

/**
 * Utility that allows running a single {@see StepInterface} in isolation for unit testing.
 * Creates all domain objects automatically using an {@see InMemoryJobRepository}.
 */
final readonly class StepRunner
{
    public function __construct(
        private InMemoryJobRepository $jobRepository = new InMemoryJobRepository(),
    ) {
    }

    public function getJobRepository(): InMemoryJobRepository
    {
        return $this->jobRepository;
    }

    /**
     * Executes the given step in a fresh {@see JobExecution} context and returns the resulting
     * {@see StepExecution} for assertion.
     */
    public function run(StepInterface $step, ?JobParameters $parameters = null): StepExecution
    {
        $parameters ??= new JobParameters([]);
        $instance = $this->jobRepository->createJobInstance('stepRunnerJob', $parameters);
        $jobExecution = $this->jobRepository->createJobExecution($instance, $parameters);
        $stepExecution = $jobExecution->createStepExecution($step->getName());

        $step->execute($stepExecution);

        return $stepExecution;
    }
}
