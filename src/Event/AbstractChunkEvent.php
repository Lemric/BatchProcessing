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

namespace Lemric\BatchProcessing\Event;

use Lemric\BatchProcessing\Chunk\ChunkContext;

abstract class AbstractChunkEvent
{
    public function __construct(public readonly ChunkContext $chunkContext)
    {
    }

    public function getChunkContext(): ChunkContext
    {
        return $this->chunkContext;
    }
}
