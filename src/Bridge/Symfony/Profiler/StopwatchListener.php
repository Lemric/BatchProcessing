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

namespace Lemric\BatchProcessing\Bridge\Symfony\Profiler;

use Lemric\BatchProcessing\Event\{AfterChunkEvent, AfterJobEvent, AfterStepEvent, BeforeChunkEvent, BeforeJobEvent, BeforeStepEvent};
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Subscribes to the framework's job/step/chunk events and emits matching
 * {@see Stopwatch} sections so they show up in the Symfony Web Profiler timeline.
 *
 * Section naming:
 *   - batch.job.{name}
 *   - batch.step.{stepName}
 *   - batch.chunk.{stepName}.{n}
 */
final class StopwatchListener
{
    /** @var array<string, int> */
    private array $chunkCounters = [];

    public function __construct(private readonly Stopwatch $stopwatch)
    {
    }

    #[AsEventListener]
    public function onAfterChunk(AfterChunkEvent $event): void
    {
        $stepName = $event->getChunkContext()->getStepExecution()->getStepName();
        $key = 'batch.step.'.$stepName;
        $name = $this->chunkSection($stepName, $this->chunkCounters[$key] ?? 0);
        if ($this->stopwatch->isStarted($name)) {
            $this->stopwatch->stop($name);
        }
    }

    #[AsEventListener]
    public function onAfterJob(AfterJobEvent $event): void
    {
        $name = $this->jobSection($event);
        if ($this->stopwatch->isStarted($name)) {
            $this->stopwatch->stop($name);
        }
    }

    #[AsEventListener]
    public function onAfterStep(AfterStepEvent $event): void
    {
        $name = 'batch.step.'.$event->getStepExecution()->getStepName();
        if ($this->stopwatch->isStarted($name)) {
            $this->stopwatch->stop($name);
        }
    }

    #[AsEventListener]
    public function onBeforeChunk(BeforeChunkEvent $event): void
    {
        $stepName = $event->getChunkContext()->getStepExecution()->getStepName();
        $key = 'batch.step.'.$stepName;
        $this->chunkCounters[$key] = ($this->chunkCounters[$key] ?? 0) + 1;
        $this->stopwatch->start($this->chunkSection($stepName, $this->chunkCounters[$key]), 'batch');
    }

    #[AsEventListener]
    public function onBeforeJob(BeforeJobEvent $event): void
    {
        $this->stopwatch->start($this->jobSection($event), 'batch');
    }

    #[AsEventListener]
    public function onBeforeStep(BeforeStepEvent $event): void
    {
        $name = 'batch.step.'.$event->getStepExecution()->getStepName();
        $this->chunkCounters[$name] = 0;
        $this->stopwatch->start($name, 'batch');
    }

    private function chunkSection(string $stepName, int $n): string
    {
        return sprintf('batch.chunk.%s.%d', $stepName, $n);
    }

    private function jobSection(BeforeJobEvent|AfterJobEvent $event): string
    {
        return 'batch.job.'.$event->getJobExecution()->getJobName();
    }
}
