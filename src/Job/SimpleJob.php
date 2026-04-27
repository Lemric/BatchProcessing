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

use DateTimeImmutable;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution, StepExecution};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\StepInterface;

use function count;

/**
 * Sequential job: runs the configured {@see StepInterface}s in declaration order. If any step
 * does not complete successfully execution stops at that point and the job is marked according
 * to the failed step's outcome.
 */
final class SimpleJob extends AbstractJob
{
    /** @var list<SplitFlow> */
    private array $splitFlows = [];

    /** @var list<StepInterface> */
    private array $steps = [];

    public function __construct(string $name, JobRepositoryInterface $jobRepository)
    {
        parent::__construct($name, $jobRepository);
    }

    public function addSplitFlow(SplitFlow $splitFlow): self
    {
        $this->splitFlows[] = $splitFlow;

        return $this;
    }

    public function addStep(StepInterface $step): self
    {
        $this->configureStep($step);
        $this->steps[] = $step;

        return $this;
    }

    /**
     * @return list<StepInterface>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    protected function doExecute(JobExecution $jobExecution): void
    {
        // On restart, bump the JobInstance version once per execution attempt.
        if ([] !== $this->jobRepository->findJobExecutions($jobExecution->getJobInstance())) {
            // Already includes the current execution itself; nothing to bump if first run.
            $instance = $jobExecution->getJobInstance();
            if (count($this->jobRepository->findJobExecutions($instance)) > 1) {
                $instance->incrementVersion();
            }
        }

        foreach ($this->steps as $step) {
            if ($jobExecution->isStopping()) {
                $jobExecution->setStatus(BatchStatus::STOPPED);

                return;
            }

            $this->interruptionPolicy?->checkInterrupted(
                $jobExecution,
                $this->jobRepository,
                $jobExecution->getJobParameters(),
            );

            $stepExecution = $this->maybeRestartStepExecution($jobExecution, $step);
            $step->execute($stepExecution);

            if ($stepExecution->getStatus()->isUnsuccessful()) {
                $jobExecution->setStatus($stepExecution->getStatus());

                return;
            }
        }

        // Execute split flows (parallel steps)
        $this->executeSplitFlows($this->splitFlows, $jobExecution);
    }

    private function maybeRestartStepExecution(JobExecution $jobExecution, StepInterface $step): StepExecution
    {
        $previous = $this->jobRepository->getLastStepExecution($jobExecution->getJobInstance(), $step->getName());

        $stepExecution = $jobExecution->createStepExecution($step->getName());
        if (null !== $previous && !$step->isAllowStartIfComplete() && BatchStatus::COMPLETED === $previous->getStatus()) {
            // Step already completed in a previous run → mark as completed without re-running.
            $stepExecution->setStatus(BatchStatus::COMPLETED);
            $stepExecution->setExitStatus(\Lemric\BatchProcessing\Domain\ExitStatus::$NOOP);
            $stepExecution->setStartTime(new DateTimeImmutable());
            $stepExecution->setEndTime(new DateTimeImmutable());
            $this->jobRepository->add($stepExecution);
            $this->jobRepository->update($stepExecution);

            return $stepExecution;
        }

        if (null !== $previous && [] !== $previous->getExecutionContext()->toArray()) {
            // Restart from the previous checkpoint.
            $stepExecution->setExecutionContext(clone $previous->getExecutionContext());
        }

        return $stepExecution;
    }
}
