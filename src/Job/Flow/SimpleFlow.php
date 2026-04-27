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

namespace Lemric\BatchProcessing\Job\Flow;

use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution};
use Lemric\BatchProcessing\Exception\FlowExecutionException;
use Lemric\BatchProcessing\Job\FlowDeciderInterface;
use Lemric\BatchProcessing\Step\StepInterface;

/**
 * Default {@see FlowInterface} implementation with conditional transitions.
 */
final class SimpleFlow implements FlowInterface
{
    /** @var array<string, FlowDeciderInterface> */
    private array $deciders = [];

    private ?string $startStepName = null;

    /** @var array<string, StepInterface> */
    private array $stepsMap = [];

    /** @var array<string, array<string, Transition>> */
    private array $transitions = [];

    public function __construct(private readonly string $name)
    {
    }

    public function addStep(StepInterface $step): void
    {
        $this->stepsMap[$step->getName()] = $step;
        $this->startStepName ??= $step->getName();
    }

    public function addTransition(string $from, string $pattern, Transition $transition): void
    {
        $this->transitions[$from][$pattern] = $transition;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function resume(string $stepName, JobExecution $jobExecution): FlowExecutionStatus
    {
        return $this->executeFrom($stepName, $jobExecution);
    }

    public function setDecider(string $stepName, FlowDeciderInterface $decider): void
    {
        $this->deciders[$stepName] = $decider;
    }

    public function setStartStep(string $name): void
    {
        $this->startStepName = $name;
    }

    public function start(JobExecution $jobExecution): FlowExecutionStatus
    {
        if (null === $this->startStepName || !isset($this->stepsMap[$this->startStepName])) {
            return FlowExecutionStatus::completed();
        }

        return $this->executeFrom($this->startStepName, $jobExecution);
    }

    private function executeFrom(string $stepName, JobExecution $jobExecution): FlowExecutionStatus
    {
        $currentStepName = $stepName;
        $lastStatus = FlowExecutionStatus::completed();

        while (null !== $currentStepName) {
            if ($jobExecution->isStopping()) {
                $jobExecution->setStatus(BatchStatus::STOPPED);

                return FlowExecutionStatus::stopped();
            }

            $step = $this->stepsMap[$currentStepName] ?? null;
            if (null === $step) {
                throw new FlowExecutionException("Step '{$currentStepName}' not found in flow '{$this->name}'.");
            }

            $stepExecution = $jobExecution->createStepExecution($step->getName());
            $step->execute($stepExecution);

            if ($stepExecution->getStatus()->isUnsuccessful()) {
                $jobExecution->setStatus($stepExecution->getStatus());
            }

            $exitCode = isset($this->deciders[$currentStepName])
                ? $this->deciders[$currentStepName]->decide($jobExecution, $stepExecution)
                : $stepExecution->getExitStatus()->getExitCode();

            $transition = $this->findTransition($currentStepName, $exitCode);
            if (null === $transition) {
                return new FlowExecutionStatus($exitCode);
            }

            if ($transition->isEnd()) {
                return new FlowExecutionStatus($transition->getStatus());
            }

            if ($transition->isFail()) {
                $jobExecution->setStatus(BatchStatus::FAILED);

                return FlowExecutionStatus::failed();
            }

            if ($transition->isStop()) {
                $jobExecution->setStatus(BatchStatus::STOPPED);

                return FlowExecutionStatus::stopped();
            }

            $currentStepName = $transition->getTo();
            $lastStatus = new FlowExecutionStatus($exitCode);
        }

        return $lastStatus;
    }

    private function findTransition(string $fromStep, string $exitCode): ?Transition
    {
        $map = $this->transitions[$fromStep] ?? [];

        return $map[$exitCode] ?? $map['*'] ?? null;
    }
}
