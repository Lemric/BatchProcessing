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

namespace Lemric\BatchProcessing\Step;

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\StepContribution;

/**
 * Atomic unit of work executed inside a transactional step. May be invoked many times per
 * step run (returning {@see RepeatStatus::CONTINUABLE}) until it returns {@see RepeatStatus::FINISHED}.
 */
interface TaskletInterface
{
    public function execute(StepContribution $contribution, ChunkContext $chunkContext): RepeatStatus;
}
