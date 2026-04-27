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

use Throwable;

/**
 * Callback interface for skip events during chunk-oriented step execution.
 */
interface SkipListenerInterface
{
    /**
     * Called when an item is skipped during the process phase.
     */
    public function onSkipInProcess(mixed $item, Throwable $t): void;

    /**
     * Called when an item is skipped during the read phase.
     */
    public function onSkipInRead(Throwable $t): void;

    /**
     * Called when an item is skipped during the write phase.
     */
    public function onSkipInWrite(mixed $item, Throwable $t): void;
}
