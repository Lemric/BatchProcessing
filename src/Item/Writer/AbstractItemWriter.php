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

namespace Lemric\BatchProcessing\Item\Writer;

use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\{ItemStreamInterface, ItemWriterInterface};

/**
 * Optional base class for writers that manage an external resource (file, connection, etc.).
 * Provides default no-op {@see ItemStreamInterface} methods that subclasses can selectively
 * override.
 *
 * @template TItem
 *
 * @implements ItemWriterInterface<TItem>
 */
abstract class AbstractItemWriter implements ItemWriterInterface, ItemStreamInterface
{
    public function close(): void
    {
    }

    public function open(ExecutionContext $executionContext): void
    {
    }

    public function update(ExecutionContext $executionContext): void
    {
    }
}
