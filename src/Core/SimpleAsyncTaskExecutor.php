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

namespace Lemric\BatchProcessing\Core;

use Fiber;
use Throwable;

/**
 * Lightweight async executor based on PHP 8.1+ Fibers.
 *
 * Tasks are wrapped in fibers and started immediately. The executor runs them cooperatively
 * — for true parallelism on CPU-bound work use a process-based executor.
 *
 * Errors thrown by the task are stored and re-thrown on the next {@see self::execute()} call
 * or via {@see self::wait()}.
 */
final class SimpleAsyncTaskExecutor implements TaskExecutorInterface
{
    /** @var list<Throwable> */
    private array $errors = [];

    /** @var list<Fiber<void, void, void, void>> */
    private array $running = [];

    public function __construct(private readonly int $concurrency = 8)
    {
    }

    public function execute(callable $task): void
    {
        $this->reapTerminated();
        while (count($this->running) >= $this->concurrency) {
            $this->tick();
        }

        $fiber = new Fiber(function () use ($task): void {
            $task();
        });

        try {
            $fiber->start();
        } catch (Throwable $e) {
            $this->errors[] = $e;

            return;
        }

        if (!$fiber->isTerminated()) {
            $this->running[] = $fiber;
        }
    }

    /**
     * Wait for all in-flight tasks to complete; re-throws the first captured error.
     */
    public function wait(): void
    {
        while ([] !== $this->running) {
            $this->tick();
        }
        if ([] !== $this->errors) {
            $first = $this->errors[0];
            $this->errors = [];
            throw $first;
        }
    }

    private function reapTerminated(): void
    {
        $this->running = array_values(array_filter($this->running, static fn (Fiber $f): bool => !$f->isTerminated()));
    }

    private function tick(): void
    {
        $still = [];
        foreach ($this->running as $fiber) {
            if ($fiber->isSuspended()) {
                try {
                    $fiber->resume();
                } catch (Throwable $e) {
                    $this->errors[] = $e;
                    continue;
                }
            }
            if (!$fiber->isTerminated()) {
                $still[] = $fiber;
            }
        }
        $this->running = $still;
    }
}
