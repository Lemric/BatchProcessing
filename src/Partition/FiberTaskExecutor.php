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

namespace Lemric\BatchProcessing\Partition;

use Fiber;
use Lemric\BatchProcessing\Domain\StepExecution;
use Lemric\BatchProcessing\Step\StepInterface;
use Throwable;

/**
 * Concurrent partition handler using PHP 8.1+ Fibers. Each partition is executed inside its
 * own Fiber, with a configurable concurrency limit. Suitable for I/O-bound workloads where
 * steps voluntarily yield (e.g. via Fiber::suspend() in async I/O adapters).
 *
 * For CPU-bound work, use the sequential {@see TaskExecutorPartitionHandler} or a
 * process-based executor instead.
 */
final class FiberTaskExecutor implements StepHandlerInterface
{
    public function __construct(
        private readonly int $maxConcurrent = 8,
    ) {
    }

    /**
     * @param list<StepExecution> $partitionStepExecutions
     */
    public function handle(StepInterface $step, array $partitionStepExecutions): void
    {
        /** @var list<Fiber<void, void, void, void>> $fibers */
        $fibers = [];
        /** @var list<Throwable> $errors */
        $errors = [];

        // Create a Fiber for each partition.
        foreach ($partitionStepExecutions as $stepExecution) {
            $fibers[] = new Fiber(static function () use ($step, $stepExecution): void {
                $step->execute($stepExecution);
            });
        }

        // Execute fibers respecting concurrency limit.
        $pending = $fibers;
        $active = [];

        while ([] !== $pending || [] !== $active) {
            // Start fibers up to maxConcurrent.
            while (count($active) < $this->maxConcurrent && [] !== $pending) {
                $fiber = array_shift($pending);
                try {
                    $fiber->start();
                } catch (Throwable $e) {
                    $errors[] = $e;
                    continue;
                }
                if (!$fiber->isTerminated()) {
                    $active[] = $fiber;
                }
            }

            // Resume suspended fibers.
            $stillActive = [];
            foreach ($active as $fiber) {
                if ($fiber->isSuspended()) {
                    try {
                        $fiber->resume();
                    } catch (Throwable $e) {
                        $errors[] = $e;
                        continue;
                    }
                }
                if (!$fiber->isTerminated()) {
                    $stillActive[] = $fiber;
                }
            }
            $active = $stillActive;
        }

        // Propagate the first error if any partition failed.
        if ([] !== $errors) {
            throw $errors[0];
        }
    }
}
