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
 * Per-chunk runtime context passed to chunk listeners.
 */
final class ChunkContext
{
    private bool $complete = false;

    public function __construct(
        private readonly StepContribution $stepContribution,
    ) {
    }

    public function getStepContribution(): StepContribution
    {
        return $this->stepContribution;
    }

    public function getStepExecution(): StepExecution
    {
        return $this->stepContribution->getStepExecution();
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function setComplete(): void
    {
        $this->complete = true;
    }
}
