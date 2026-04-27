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

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Listener\Support\ChunkListenerSupport;

final class ChunkListenerAdapter extends ChunkListenerSupport
{
    use DispatchesHooks;

    public function afterChunk(ChunkContext $context): void
    {
        $this->dispatch('afterChunk', $context);
    }

    public function beforeChunk(ChunkContext $context): void
    {
        $this->dispatch('beforeChunk', $context);
    }
}
