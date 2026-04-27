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
use Lemric\BatchProcessing\Domain\StepContribution;
use Lemric\BatchProcessing\Step\RepeatStatus;
use Throwable;

/**
 * Reference implementation of {@see RepeatOperationsInterface}.
 * Drives a {@see CompletionPolicyInterface} to decide when to stop iterating.
 * Integrates {@see RepeatContext} and {@see RepeatListenerInterface} lifecycle.
 */
final class RepeatTemplate implements RepeatOperationsInterface
{
    /** @var list<RepeatListenerInterface> */
    private array $listeners = [];

    public function __construct(
        private readonly CompletionPolicyInterface $completionPolicy = new SimpleCompletionPolicy(10),
    ) {
    }

    public function iterate(callable $callback): RepeatStatus
    {
        $chunkContext = new ChunkContext(new StepContribution(
            new \Lemric\BatchProcessing\Domain\StepExecution(
                'repeat',
                new \Lemric\BatchProcessing\Domain\JobExecution(
                    null,
                    new \Lemric\BatchProcessing\Domain\JobInstance(null, 'repeat', 'repeat'),
                    new \Lemric\BatchProcessing\Domain\JobParameters(),
                ),
            ),
        ));

        $repeatContext = new RepeatContext();

        $this->completionPolicy->start($chunkContext);

        // Notify listeners: open
        foreach ($this->listeners as $listener) {
            $listener->open($repeatContext);
        }

        $result = RepeatStatus::CONTINUABLE;

        try {
            while ($result->isContinuable()) {
                if ($repeatContext->isTerminateOnly()) {
                    $result = RepeatStatus::FINISHED;
                    break;
                }

                // Notify listeners: before
                foreach ($this->listeners as $listener) {
                    $listener->before($repeatContext);
                }

                $repeatContext->increment();

                try {
                    $result = $callback();
                } catch (Throwable $e) {
                    // Notify listeners: onError
                    foreach ($this->listeners as $listener) {
                        $listener->onError($repeatContext, $e);
                    }
                    throw $e;
                }

                // Notify listeners: after
                foreach ($this->listeners as $listener) {
                    $listener->after($repeatContext, $result);
                }

                $this->completionPolicy->update($chunkContext);

                if ($this->completionPolicy->isComplete($chunkContext, $result)) {
                    break;
                }

                if ($repeatContext->isCompleteOnly()) {
                    break;
                }
            }
        } finally {
            // Notify listeners: close
            foreach ($this->listeners as $listener) {
                $listener->close($repeatContext);
            }
        }

        return $result;
    }

    public function registerListener(RepeatListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }
}
