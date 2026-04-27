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

use Lemric\BatchProcessing\Chunk\{Chunk, ChunkContext};
use Lemric\BatchProcessing\Domain\{ExitStatus, JobExecution, StepExecution};
use Throwable;

/**
 * Aggregates listeners of all kinds into a single dispatch surface used by the framework. Each
 * delegate is invoked in registration order; exceptions raised by listeners are propagated.
 */
final class CompositeListener implements JobExecutionListenerInterface, StepExecutionListenerInterface, ChunkListenerInterface, ItemReadListenerInterface, ItemProcessListenerInterface, ItemWriteListenerInterface, SkipListenerInterface
{
    /** @var list<object> */
    private array $listeners = [];

    public function afterChunk(ChunkContext $context): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ChunkListenerInterface) {
                $l->afterChunk($context);
            }
        }
    }

    public function afterChunkError(ChunkContext $context, Throwable $t): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ChunkListenerInterface) {
                $l->afterChunkError($context, $t);
            }
        }
    }

    public function afterJob(JobExecution $jobExecution): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof JobExecutionListenerInterface) {
                $l->afterJob($jobExecution);
            }
        }
    }

    public function afterProcess(mixed $item, mixed $result): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemProcessListenerInterface) {
                $l->afterProcess($item, $result);
            }
        }
    }

    public function afterRead(mixed $item): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemReadListenerInterface) {
                $l->afterRead($item);
            }
        }
    }

    public function afterStep(StepExecution $stepExecution): ExitStatus
    {
        $current = $stepExecution->getExitStatus();
        foreach ($this->listeners as $l) {
            if ($l instanceof StepExecutionListenerInterface) {
                $maybe = $l->afterStep($stepExecution);
                if (null !== $maybe) {
                    $current = $current->and($maybe);
                }
            }
        }

        return $current;
    }

    /**
     * @param Chunk<mixed, mixed> $items
     */
    public function afterWrite(Chunk $items): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemWriteListenerInterface) {
                $l->afterWrite($items);
            }
        }
    }

    public function beforeChunk(ChunkContext $context): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ChunkListenerInterface) {
                $l->beforeChunk($context);
            }
        }
    }

    public function beforeJob(JobExecution $jobExecution): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof JobExecutionListenerInterface) {
                $l->beforeJob($jobExecution);
            }
        }
    }

    public function beforeProcess(mixed $item): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemProcessListenerInterface) {
                $l->beforeProcess($item);
            }
        }
    }

    public function beforeRead(): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemReadListenerInterface) {
                $l->beforeRead();
            }
        }
    }

    public function beforeStep(StepExecution $stepExecution): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof StepExecutionListenerInterface) {
                $l->beforeStep($stepExecution);
            }
        }
    }

    /**
     * @param Chunk<mixed, mixed> $items
     */
    public function beforeWrite(Chunk $items): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemWriteListenerInterface) {
                $l->beforeWrite($items);
            }
        }
    }

    /**
     * @return list<object>
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    public function isEmpty(): bool
    {
        return [] === $this->listeners;
    }

    public function onProcessError(mixed $item, Throwable $t): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemProcessListenerInterface) {
                $l->onProcessError($item, $t);
            }
        }
    }

    public function onReadError(Throwable $t): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemReadListenerInterface) {
                $l->onReadError($t);
            }
        }
    }

    public function onSkipInProcess(mixed $item, Throwable $t): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof SkipListenerInterface) {
                $l->onSkipInProcess($item, $t);
            }
        }
    }

    public function onSkipInRead(Throwable $t): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof SkipListenerInterface) {
                $l->onSkipInRead($t);
            }
        }
    }

    public function onSkipInWrite(mixed $item, Throwable $t): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof SkipListenerInterface) {
                $l->onSkipInWrite($item, $t);
            }
        }
    }

    /**
     * @param Chunk<mixed, mixed> $items
     */
    public function onWriteError(Throwable $t, Chunk $items): void
    {
        foreach ($this->listeners as $l) {
            if ($l instanceof ItemWriteListenerInterface) {
                $l->onWriteError($t, $items);
            }
        }
    }

    public function register(object $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * @param iterable<object> $listeners
     */
    public function registerAll(iterable $listeners): void
    {
        foreach ($listeners as $listener) {
            $this->register($listener);
        }
    }
}
