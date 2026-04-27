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

namespace Lemric\BatchProcessing\Repeat\Executor;

/**
 * Minimal task executor abstraction used by {@see \Lemric\BatchProcessing\Repeat\TaskExecutorRepeatTemplate}.
 * Intentionally narrower than the full {@see \Lemric\BatchProcessing\Core\TaskExecutorInterface}
 * to avoid coupling the Repeat package to runtime async primitives.
 */
interface TaskExecutorInterface
{
    /**
     * Submits the task for (potentially asynchronous) execution. Implementations are free to
     * run the task synchronously — see {@see SyncTaskExecutor}.
     *
     * @param callable(): mixed $task
     */
    public function execute(callable $task): mixed;
}
