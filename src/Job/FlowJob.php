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

use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution, StepExecution};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\StepInterface;

/**
 * Job with conditional step flow. Steps are connected via transitions:
 *
 *     $flow->from($step1)->on('COMPLETED')->to($step2);
 *     $flow->from($step1)->on('FAILED')->end();
 *
 * After each step, the exit code is evaluated against registered transitions to decide the next
 * step. If no transition matches, the job ends. An optional {@see FlowDeciderInterface} may be
 * used to make programmatic routing decisions.
 */
final class FlowJob extends AbstractJob
{
    /** Sentinel values for special transition actions */
    public const string TRANSITION_FAIL = '___FAIL___';

    public const string TRANSITION_STOP = '___STOP___';

    private const string TRANSITION_END_PREFIX = '___END:';

    /** @var array<string, FlowDeciderInterface> stepName → decider */
    private array $deciders = [];

    /** @var list<SplitFlow> */
    private array $splitFlows = [];

    private ?string $startStepName = null;

    /** @var list<StepInterface> */
    private array $steps = [];

    /**
     * @var array<string, array<string, string|null>>
     *                                                stepName → [exitCode → nextStepName|null (=end)|sentinel]
     */
    private array $transitions = [];

    public function __construct(string $name, JobRepositoryInterface $jobRepository)
    {
        parent::__construct($name, $jobRepository);
    }

    /**
     * On exit code, end the job with a custom exit status.
     */
    public function addEndTransition(string $fromStepName, string $exitCode, string $exitStatus = 'COMPLETED'): self
    {
        $this->transitions[$fromStepName][$exitCode] = self::TRANSITION_END_PREFIX.$exitStatus.'___';

        return $this;
    }

    /**
     * On exit code, fail the job.
     */
    public function addFailTransition(string $fromStepName, string $exitCode): self
    {
        $this->transitions[$fromStepName][$exitCode] = self::TRANSITION_FAIL;

        return $this;
    }

    public function addSplitFlow(SplitFlow $splitFlow): self
    {
        $this->splitFlows[] = $splitFlow;

        return $this;
    }

    public function addStep(StepInterface $step): self
    {
        $this->configureStep($step);

        // Avoid duplicates.
        foreach ($this->steps as $existing) {
            if ($existing->getName() === $step->getName()) {
                return $this;
            }
        }

        $this->steps[] = $step;

        return $this;
    }

    /**
     * On exit code, stop the job (restartable).
     */
    public function addStopTransition(string $fromStepName, string $exitCode): self
    {
        $this->transitions[$fromStepName][$exitCode] = self::TRANSITION_STOP;

        return $this;
    }

    /**
     * Registers a transition: when {@code $fromStep} finishes with {@code $exitCode},
     * continue to {@code $toStep}. Pass {@code null} for {@code $toStep} to end the job.
     */
    public function addTransition(string $fromStepName, string $exitCode, ?string $toStepName): self
    {
        $this->transitions[$fromStepName][$exitCode] = $toStepName;

        return $this;
    }

    /**
     * @return list<StepInterface>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Registers a programmatic decider that is consulted after the given step instead of
     * using the step's exit code directly.
     */
    public function setDecider(string $stepName, FlowDeciderInterface $decider): self
    {
        $this->deciders[$stepName] = $decider;

        return $this;
    }

    /**
     * Sets the first step to execute.
     */
    public function setStartStep(StepInterface $step): self
    {
        $this->startStepName = $step->getName();
        $this->addStep($step);

        return $this;
    }

    protected function doExecute(JobExecution $jobExecution): void
    {
        $currentStep = $this->resolveStartStep();
        if (null === $currentStep) {
            return;
        }

        while (null !== $currentStep) {
            if ($jobExecution->isStopping()) {
                $jobExecution->setStatus(BatchStatus::STOPPED);

                return;
            }

            $stepExecution = $jobExecution->createStepExecution($currentStep->getName());
            $currentStep->execute($stepExecution);

            if ($stepExecution->getStatus()->isUnsuccessful()) {
                $jobExecution->setStatus($stepExecution->getStatus());
            }

            $currentStep = $this->resolveNext($currentStep->getName(), $stepExecution, $jobExecution);
        }

        // Execute any registered parallel split flows after the conditional flow has finished.
        $this->executeSplitFlows($this->splitFlows, $jobExecution);
    }

    private function findStep(string $name): ?StepInterface
    {
        return array_find($this->steps, fn ($step) => $step->getName() === $name);
    }

    private function resolveNext(
        string $fromStepName,
        StepExecution $stepExecution,
        JobExecution $jobExecution,
    ): ?StepInterface {
        $exitCode = isset($this->deciders[$fromStepName])
            ? $this->deciders[$fromStepName]->decide($jobExecution, $stepExecution)
            : $stepExecution->getExitStatus()->getExitCode();

        $map = $this->transitions[$fromStepName] ?? [];

        // Try exact match first, then wildcard '*'.
        $nextStepName = $map[$exitCode] ?? $map['*'] ?? null;

        if (null === $nextStepName) {
            // No matching transition → end of flow.
            return null;
        }

        // Handle sentinel transitions
        if (self::TRANSITION_FAIL === $nextStepName) {
            $jobExecution->setStatus(BatchStatus::FAILED);

            return null;
        }

        if (self::TRANSITION_STOP === $nextStepName) {
            $jobExecution->setStatus(BatchStatus::STOPPED);

            return null;
        }

        if (str_starts_with($nextStepName, self::TRANSITION_END_PREFIX)) {
            $customExitStatus = mb_substr($nextStepName, mb_strlen(self::TRANSITION_END_PREFIX), -3);
            $jobExecution->setExitStatus(new \Lemric\BatchProcessing\Domain\ExitStatus($customExitStatus));

            return null;
        }

        return $this->findStep($nextStepName);
    }

    private function resolveStartStep(): ?StepInterface
    {
        if (null !== $this->startStepName) {
            return $this->findStep($this->startStepName);
        }

        return $this->steps[0] ?? null;
    }
}
