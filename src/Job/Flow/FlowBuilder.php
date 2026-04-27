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

use Lemric\BatchProcessing\Job\FlowDeciderInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\{FlowStep, StepInterface};
use LogicException;

/**
 * Fluent builder for {@see SimpleFlow} configurations.
 *
 * Usage:
 *   $flow = FlowBuilder::create('myflow')
 *       ->from($step1)->on('COMPLETED')->to($step2)
 *       ->from($step1)->on('FAILED')->fail()
 *       ->from($step2)->on('*')->end()
 *       ->build();
 */
final class FlowBuilder
{
    private ?string $currentFrom = null;

    private ?string $currentPattern = null;

    private SimpleFlow $flow;

    private function __construct(string $name)
    {
        $this->flow = new SimpleFlow($name);
    }

    public function build(): SimpleFlow
    {
        return $this->flow;
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function decider(string $afterStepName, FlowDeciderInterface $decider): self
    {
        $this->flow->setDecider($afterStepName, $decider);

        return $this;
    }

    public function end(string $exitStatus = 'COMPLETED'): self
    {
        $this->addTransition(Transition::end($exitStatus));

        return $this;
    }

    public function fail(): self
    {
        $this->addTransition(Transition::fail());

        return $this;
    }

    public function from(StepInterface $step): self
    {
        $this->flow->addStep($step);
        $this->currentFrom = $step->getName();
        $this->currentPattern = null;

        return $this;
    }

    /**
     * Nests an entire {@see FlowInterface} as a single step in the parent flow. Requires a {@see JobRepositoryInterface} so the wrapping {@see FlowStep} can
     * be constructed.
     */
    public function fromFlow(FlowInterface $flow, JobRepositoryInterface $jobRepository): self
    {
        $wrapper = new FlowStep($flow->getName(), $jobRepository, $flow);

        return $this->from($wrapper);
    }

    /**
     * Adds a nested {@see FlowInterface} as the next sequential step.
     */
    public function nextFlow(FlowInterface $flow, JobRepositoryInterface $jobRepository): self
    {
        $wrapper = new FlowStep($flow->getName(), $jobRepository, $flow);

        return $this->to($wrapper);
    }

    public function on(string $exitCodePattern): self
    {
        $this->currentPattern = $exitCodePattern;

        return $this;
    }

    public function start(StepInterface $step): self
    {
        $this->flow->addStep($step);
        $this->flow->setStartStep($step->getName());
        $this->currentFrom = $step->getName();

        return $this;
    }

    public function stop(): self
    {
        $this->addTransition(Transition::stop());

        return $this;
    }

    public function stopAndRestart(StepInterface $restartStep): self
    {
        $this->flow->addStep($restartStep);
        $this->addTransition(Transition::stopAndRestart($restartStep->getName()));

        return $this;
    }

    public function to(StepInterface $step): self
    {
        $this->flow->addStep($step);
        $this->addTransition(Transition::to($step->getName()));

        return $this;
    }

    private function addTransition(Transition $transition): void
    {
        if (null === $this->currentFrom || null === $this->currentPattern) {
            throw new LogicException('Call from() and on() before defining a transition target.');
        }
        $this->flow->addTransition($this->currentFrom, $this->currentPattern, $transition);
    }
}
