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

namespace Lemric\BatchProcessing\Domain;

/**
 * Per-chunk contribution of statistics to be applied to the parent {@see StepExecution}.
 *
 * Allows a chunk to be staged transactionally: the contribution is only applied (folded into
 * the StepExecution) once the chunk has been successfully committed.
 */
final class StepContribution
{
    private ExitStatus $exitStatus;

    private int $filterCount = 0;

    private int $processSkipCount = 0;

    private int $readCount = 0;

    private int $readSkipCount = 0;

    private int $writeCount = 0;

    private int $writeSkipCount = 0;

    public function __construct(private readonly StepExecution $stepExecution)
    {
        $this->exitStatus = ExitStatus::$EXECUTING;
    }

    /**
     * Folds this contribution into the parent {@see StepExecution}.
     */
    public function apply(): void
    {
        if ($this->readCount > 0) {
            $this->stepExecution->incrementReadCount($this->readCount);
        }
        if ($this->writeCount > 0) {
            $this->stepExecution->incrementWriteCount($this->writeCount);
        }
        if ($this->filterCount > 0) {
            $this->stepExecution->incrementFilterCount($this->filterCount);
        }
        if ($this->readSkipCount > 0) {
            $this->stepExecution->incrementReadSkipCount($this->readSkipCount);
        }
        if ($this->processSkipCount > 0) {
            $this->stepExecution->incrementProcessSkipCount($this->processSkipCount);
        }
        if ($this->writeSkipCount > 0) {
            $this->stepExecution->incrementWriteSkipCount($this->writeSkipCount);
        }
    }

    /**
     * Merge counters from another contribution (used by threaded chunk processors).
     */
    public function combine(StepContribution $other): void
    {
        $this->readCount += $other->readCount;
        $this->writeCount += $other->writeCount;
        $this->filterCount += $other->filterCount;
        $this->readSkipCount += $other->readSkipCount;
        $this->processSkipCount += $other->processSkipCount;
        $this->writeSkipCount += $other->writeSkipCount;
    }

    public function getExitStatus(): ExitStatus
    {
        return $this->exitStatus;
    }

    public function getFilterCount(): int
    {
        return $this->filterCount;
    }

    public function getProcessSkipCount(): int
    {
        return $this->processSkipCount;
    }

    public function getReadCount(): int
    {
        return $this->readCount;
    }

    public function getReadSkipCount(): int
    {
        return $this->readSkipCount;
    }

    public function getStepExecution(): StepExecution
    {
        return $this->stepExecution;
    }

    public function getStepSkipCount(): int
    {
        return $this->stepExecution->getSkipCount();
    }

    public function getWriteCount(): int
    {
        return $this->writeCount;
    }

    public function getWriteSkipCount(): int
    {
        return $this->writeSkipCount;
    }

    public function incrementFilterCount(int $by = 1): void
    {
        $this->filterCount += $by;
    }

    public function incrementProcessSkipCount(): void
    {
        ++$this->processSkipCount;
    }

    public function incrementReadCount(int $by = 1): void
    {
        $this->readCount += $by;
    }

    public function incrementReadSkipCount(): void
    {
        ++$this->readSkipCount;
    }

    public function incrementWriteCount(int $by): void
    {
        $this->writeCount += $by;
    }

    public function incrementWriteSkipCount(): void
    {
        ++$this->writeSkipCount;
    }

    public function setExitStatus(ExitStatus $exitStatus): void
    {
        $this->exitStatus = $exitStatus;
    }
}
