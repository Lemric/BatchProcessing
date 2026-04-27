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

namespace Lemric\BatchProcessing\Listener\Adapter;

use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Listener\Support\ItemWriteListenerSupport;
use Throwable;

final class ItemWriteListenerAdapter extends ItemWriteListenerSupport
{
    use DispatchesHooks;

    public function afterWrite(Chunk $items): void
    {
        $this->dispatch('afterWrite', $items);
    }

    public function beforeWrite(Chunk $items): void
    {
        $this->dispatch('beforeWrite', $items);
    }

    public function onWriteError(Throwable $t, Chunk $items): void
    {
        $this->dispatch('onWriteError', $t, $items);
    }
}
