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
 * Synchronous in-thread executor — invokes the task immediately. Default for
 * {@see \Lemric\BatchProcessing\Repeat\TaskExecutorRepeatTemplate} on PHP-FPM.
 */
final class SyncTaskExecutor implements TaskExecutorInterface
{
    public function execute(callable $task): mixed
    {
        return $task();
    }
}
