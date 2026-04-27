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

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Throwable;

interface ChunkListenerInterface
{
    public function afterChunk(ChunkContext $context): void;

    public function afterChunkError(ChunkContext $context, Throwable $t): void;

    public function beforeChunk(ChunkContext $context): void;
}
