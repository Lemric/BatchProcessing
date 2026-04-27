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

namespace Lemric\BatchProcessing\Chunk;

use Lemric\BatchProcessing\Listener\ChunkListenerInterface;

/**
 * Package-level alias for the canonical {@see ChunkListenerInterface}.
 * Listed in the Chunk namespace per spec §3.1 for discoverability.
 */
interface ChunkListener extends ChunkListenerInterface
{
}
