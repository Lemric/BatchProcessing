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

namespace Lemric\BatchProcessing\Listener\Support;

use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Listener\ItemWriteListenerInterface;
use Throwable;

abstract class ItemWriteListenerSupport implements ItemWriteListenerInterface
{
    public function afterWrite(Chunk $items): void
    {
    }

    public function beforeWrite(Chunk $items): void
    {
    }

    public function onWriteError(Throwable $t, Chunk $items): void
    {
    }
}
