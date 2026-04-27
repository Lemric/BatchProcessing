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

/**
 * Abstraction of "execute this callable somewhere" — sync, async, fiber, or process.
 */
interface TaskExecutorInterface
{
    /**
     * @param callable(): void $task
     */
    public function execute(callable $task): void;
}
