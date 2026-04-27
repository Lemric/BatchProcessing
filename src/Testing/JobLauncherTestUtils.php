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

use Lemric\BatchProcessing\Domain\{ExecutionContext, JobExecution, JobInstance, JobParameters, StepExecution};
use Lemric\BatchProcessing\Job\JobInterface;
use Lemric\BatchProcessing\Launcher\SimpleJobLauncher;
use Lemric\BatchProcessing\Repository\{InMemoryJobRepository, JobRepositoryInterface};
use Lemric\BatchProcessing\Step\StepInterface;

/**
 * Convenience helper for unit / integration tests of jobs. Wires up an in-memory repository,
 * a synchronous launcher and exposes a tiny set of assertions-friendly helpers.
 */
final readonly class JobLauncherTestUtils
{
    private SimpleJobLauncher $launcher;

    private JobRepositoryInterface $repository;

    public function __construct(?JobRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new InMemoryJobRepository();
        $this->launcher = new SimpleJobLauncher($this->repository);
    }

    public function getRepository(): JobRepositoryInterface
    {
        return $this->repository;
    }

    public function launchJob(JobInterface $job, JobParameters $parameters): JobExecution
    {
        return $this->launcher->run($job, $parameters);
    }

    /**
     * Launch a single step outside of a full job execution.
     */
    public function launchStep(StepInterface $step, ?ExecutionContext $executionContext = null): StepExecution
    {
        $jobInstance = new JobInstance(null, 'test-job', 'test-job');
        $jobExecution = new JobExecution(null, $jobInstance, JobParameters::empty());
        $stepExecution = $jobExecution->createStepExecution($step->getName());

        if (null !== $executionContext) {
            $stepExecution->getExecutionContext()->merge($executionContext);
        }

        $step->execute($stepExecution);

        return $stepExecution;
    }
}
