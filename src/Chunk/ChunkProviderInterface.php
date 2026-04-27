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

use Lemric\BatchProcessing\Domain\{StepContribution, StepExecution};

/**
 * Produces a {@see Chunk} of input items by reading from the configured
 * source. Read-side concerns (skip-on-read, transactional read queue, retry) live here.
 *
 * @template TIn
 */
interface ChunkProviderInterface
{
    /**
     * @return Chunk<TIn, mixed>
     */
    public function provide(StepExecution $stepExecution, StepContribution $contribution): Chunk;
}
