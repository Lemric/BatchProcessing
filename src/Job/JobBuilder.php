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

use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\StepInterface;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Fluent builder for {@see SimpleJob} and {@see FlowJob} configurations.
 */
final class JobBuilder
{
    private bool $allowStartIfComplete = false;

    /** @var array<string, FlowDeciderInterface> */
    private array $deciders = [];

    private ?EventDispatcherInterface $dispatcher = null;

    private bool $flowMode = false;

    private ?JobParametersIncrementerInterface $incrementer = null;

    private ?JobInterruptionPolicyInterface $interruptionPolicy = null;

    /** @var list<object> */
    private array $listeners = [];

    private ?LoggerInterface $logger = null;

    private bool $restartable = true;

    /** @var list<SplitFlow> */
    private array $splitFlows = [];

    private ?string $startStepName = null;

    /** @var list<StepInterface> */
    private array $steps = [];

    /** @var array<string, array<string, ?string>> */
    private array $transitions = [];

    private ?JobParametersValidatorInterface $validator = null;

    public function __construct(
        private readonly string $name,
        private readonly JobRepositoryInterface $jobRepository,
    ) {
    }

    public function allowStartIfComplete(bool $value = true): self
    {
        $this->allowStartIfComplete = $value;

        return $this;
    }

    public function build(): JobInterface
    {
        if ($this->flowMode) {
            $job = new FlowJob($this->name, $this->jobRepository);
        } else {
            $job = new SimpleJob($this->name, $this->jobRepository);
        }
        $job->setRestartable($this->restartable);
        $job->setAllowStartIfComplete($this->allowStartIfComplete);
        if (null !== $this->interruptionPolicy) {
            $job->setInterruptionPolicy($this->interruptionPolicy);
        }
        if (null !== $this->incrementer) {
            $job->setIncrementer($this->incrementer);
        }
        if (null !== $this->dispatcher) {
            $job->setEventDispatcher($this->dispatcher);
        }
        if (null !== $this->logger) {
            $job->setLogger($this->logger);
        }
        if (null !== $this->validator) {
            $job->setValidator($this->validator);
        }
        foreach ($this->listeners as $listener) {
            $job->registerListener($listener);
        }
        if ($job instanceof FlowJob) {
            foreach ($this->steps as $step) {
                $job->addStep($step);
            }
            if (null !== $this->startStepName) {
                foreach ($this->steps as $step) {
                    if ($step->getName() === $this->startStepName) {
                        $job->setStartStep($step);
                        break;
                    }
                }
            }
            foreach ($this->transitions as $from => $map) {
                foreach ($map as $exit => $to) {
                    $job->addTransition($from, $exit, $to);
                }
            }
            foreach ($this->deciders as $stepName => $decider) {
                $job->setDecider($stepName, $decider);
            }
            foreach ($this->splitFlows as $splitFlow) {
                $job->addSplitFlow($splitFlow);
            }
        } else {
            foreach ($this->steps as $step) {
                $job->addStep($step);
            }
            // $job is guaranteed to be a SimpleJob here (the only non-flow branch).
            foreach ($this->splitFlows as $splitFlow) {
                $job->addSplitFlow($splitFlow);
            }
        }

        return $job;
    }

    /**
     * Registers a programmatic {@see FlowDeciderInterface} consulted instead of the step's
     * exit code when computing the next transition.
     */
    public function decider(StepInterface $afterStep, FlowDeciderInterface $decider): self
    {
        if (!$this->flowMode) {
            throw new LogicException('decider() requires flow() mode.');
        }
        $this->deciders[$afterStep->getName()] = $decider;
        $this->ensureStep($afterStep);

        return $this;
    }

    public function eventDispatcher(EventDispatcherInterface $dispatcher): self
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    /**
     * Switches the builder into flow mode: {@see build()} will produce a {@see FlowJob} and
     * {@see transition()} / {@see decider()} become available.
     */
    public function flow(): self
    {
        $this->flowMode = true;

        return $this;
    }

    public function incrementer(JobParametersIncrementerInterface $incrementer): self
    {
        $this->incrementer = $incrementer;

        return $this;
    }

    public function interruptionPolicy(JobInterruptionPolicyInterface $policy): self
    {
        $this->interruptionPolicy = $policy;

        return $this;
    }

    public function listener(object $listener): self
    {
        $this->listeners[] = $listener;

        return $this;
    }

    public function logger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function next(StepInterface $step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    public function preventRestart(bool $value = true): self
    {
        $this->restartable = !$value;

        return $this;
    }

    /**
     * Registers a parallel split: multiple steps executed concurrently using Fibers.
     */
    public function split(StepInterface ...$steps): self
    {
        $this->splitFlows[] = new SplitFlow(...$steps);

        return $this;
    }

    public function start(StepInterface $step): self
    {
        $this->steps = [$step];
        if ($this->flowMode) {
            $this->startStepName = $step->getName();
        }

        return $this;
    }

    /**
     * Registers a transition for {@see FlowJob}: when {@code $from} finishes with
     * {@code $exitCode} the flow continues to {@code $to} (or ends when {@code $to} is null).
     * {@code $exitCode} may be the wildcard '*'.
     */
    public function transition(StepInterface $from, string $exitCode, ?StepInterface $to): self
    {
        if (!$this->flowMode) {
            throw new LogicException('transition() requires flow() mode.');
        }
        $this->transitions[$from->getName()][$exitCode] = $to?->getName();
        $this->ensureStep($from);
        if (null !== $to) {
            $this->ensureStep($to);
        }

        return $this;
    }

    public function validator(JobParametersValidatorInterface $validator): self
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Convenience for the most common {@see RunIdIncrementer} setup.
     */
    public function withRunIdIncrementer(string $key = 'run.id'): self
    {
        return $this->incrementer(new RunIdIncrementer($key));
    }

    private function ensureStep(StepInterface $step): void
    {
        if (array_any($this->steps, fn ($existing) => $existing->getName() === $step->getName())) {
            return;
        }
        $this->steps[] = $step;
    }
}
