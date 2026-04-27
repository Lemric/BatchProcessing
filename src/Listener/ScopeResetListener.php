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

namespace Lemric\BatchProcessing\Listener;

use Lemric\BatchProcessing\Domain\{JobExecution, StepExecution};
use Lemric\BatchProcessing\Event\{AfterJobEvent, AfterStepEvent, BeforeJobEvent, BeforeStepEvent};
use Lemric\BatchProcessing\Scope\Container\ScopedContainerInterface;

/**
 * Lifecycle bridge between the framework events and one or two
 * {@see ScopedContainerInterface} instances (typically one for {@code job} scope and one for
 * {@code step} scope).
 */
final class ScopeResetListener
{
    public function __construct(
        private readonly ?ScopedContainerInterface $jobScopeContainer = null,
        private readonly ?ScopedContainerInterface $stepScopeContainer = null,
    ) {
    }

    public function onAfterJob(AfterJobEvent $event): void
    {
        $this->jobScopeContainer?->resetScope($this->jobScopeId($event->getJobExecution()));
    }

    public function onAfterStep(AfterStepEvent $event): void
    {
        $this->stepScopeContainer?->resetScope($this->stepScopeId($event->getStepExecution()));
    }

    public function onBeforeJob(BeforeJobEvent $event): void
    {
        $this->jobScopeContainer?->enterScope($this->jobScopeId($event->getJobExecution()));
    }

    public function onBeforeStep(BeforeStepEvent $event): void
    {
        $this->stepScopeContainer?->enterScope($this->stepScopeId($event->getStepExecution()));
    }

    private function jobScopeId(JobExecution $execution): string
    {
        return 'job-'.($execution->getId() ?? spl_object_id($execution));
    }

    private function stepScopeId(StepExecution $execution): string
    {
        return 'step-'.($execution->getId() ?? spl_object_id($execution));
    }
}
