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

namespace Lemric\BatchProcessing\Item;

use Lemric\BatchProcessing\Domain\ExecutionContext;

/**
 * Lifecycle interface for stateful resources (cursors, files, network connections, etc.).
 *
 * The framework calls {@see open()} once before the first read/write, {@see update()} after every
 * successful chunk commit (this is the checkpoint that enables restart) and {@see close()} once
 * the step finishes (success, failure or interrupt).
 */
interface ItemStreamInterface
{
    /**
     * Releases the resource. Must be safe to call even when {@see open()} failed.
     */
    public function close(): void;

    /**
     * Opens the underlying resource. On restart, the supplied {@see ExecutionContext} contains
     * the previously persisted state and the implementation should restore its position.
     */
    public function open(ExecutionContext $executionContext): void;

    /**
     * Persists the current resource state into the supplied {@see ExecutionContext}.
     */
    public function update(ExecutionContext $executionContext): void;
}
