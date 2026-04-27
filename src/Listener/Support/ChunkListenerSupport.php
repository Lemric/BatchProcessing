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

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Listener\ChunkListenerInterface;
use Throwable;

abstract class ChunkListenerSupport implements ChunkListenerInterface
{
    public function afterChunk(ChunkContext $context): void
    {
    }

    public function afterChunkError(ChunkContext $context, Throwable $t): void
    {
    }

    public function beforeChunk(ChunkContext $context): void
    {
    }
}
