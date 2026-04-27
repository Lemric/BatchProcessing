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

namespace Lemric\BatchProcessing\Repeat;

use Lemric\BatchProcessing\Chunk\{ChunkContext, CompletionPolicyInterface, SimpleCompletionPolicy};
use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters, StepContribution, StepExecution};
use Lemric\BatchProcessing\Repeat\Executor\{SyncTaskExecutor, TaskExecutorInterface};
use Lemric\BatchProcessing\Step\RepeatStatus;
use Throwable;

/**
 * {@code TaskExecutorRepeatTemplate} parity: drives a {@see RepeatOperationsInterface}
 * loop while submitting each iteration to a {@see TaskExecutorInterface}. Default executor is
 * synchronous (matching PHP-FPM realities); pcntl/amphp executors can be plugged in.
 *
 * Aggregation: the resulting {@see RepeatStatus} is the most-restrictive of all child statuses
 * (FINISHED beats CONTINUABLE).
 */
final class TaskExecutorRepeatTemplate implements RepeatOperationsInterface
{
    /** @var list<RepeatListenerInterface> */
    private array $listeners = [];

    public function __construct(
        private readonly CompletionPolicyInterface $completionPolicy = new SimpleCompletionPolicy(10),
        private readonly TaskExecutorInterface $taskExecutor = new SyncTaskExecutor(),
    ) {
    }

    public function iterate(callable $callback): RepeatStatus
    {
        $chunkContext = new ChunkContext(new StepContribution(
            new StepExecution(
                'repeat',
                new JobExecution(null, new JobInstance(null, 'repeat', 'repeat'), new JobParameters()),
            ),
        ));

        $repeatContext = new RepeatContext();
        $this->completionPolicy->start($chunkContext);

        foreach ($this->listeners as $listener) {
            $listener->open($repeatContext);
        }

        $aggregate = RepeatStatus::CONTINUABLE;

        try {
            while ($aggregate->isContinuable()) {
                if ($repeatContext->isTerminateOnly()) {
                    $aggregate = RepeatStatus::FINISHED;
                    break;
                }

                foreach ($this->listeners as $listener) {
                    $listener->before($repeatContext);
                }
                $repeatContext->increment();

                try {
                    /** @var RepeatStatus $childStatus */
                    $childStatus = $this->taskExecutor->execute(static fn () => $callback());
                } catch (Throwable $e) {
                    foreach ($this->listeners as $listener) {
                        $listener->onError($repeatContext, $e);
                    }
                    throw $e;
                }

                foreach ($this->listeners as $listener) {
                    $listener->after($repeatContext, $childStatus);
                }

                $this->completionPolicy->update($chunkContext);

                // Aggregation: FINISHED dominates CONTINUABLE.
                if (!$childStatus->isContinuable()) {
                    $aggregate = RepeatStatus::FINISHED;
                }

                if ($this->completionPolicy->isComplete($chunkContext, $childStatus)) {
                    $aggregate = RepeatStatus::FINISHED;
                    break;
                }

                if ($repeatContext->isCompleteOnly()) {
                    $aggregate = RepeatStatus::FINISHED;
                    break;
                }
            }
        } finally {
            foreach ($this->listeners as $listener) {
                $listener->close($repeatContext);
            }
        }

        return $aggregate;
    }

    public function registerListener(RepeatListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }
}
