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
 * Processes a previously read {@see Chunk} (process + write inside the
 * configured transactional boundary). Process- and write-side concerns (retry, skip, scan,
 * noRollback, processorNonTransactional) live here.
 *
 * @template TIn
 * @template TOut
 */
interface ChunkProcessorInterface
{
    /**
     * @param Chunk<TIn, TOut> $chunk
     */
    public function process(StepExecution $stepExecution, StepContribution $contribution, Chunk $chunk): void;
}
