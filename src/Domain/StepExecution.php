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

use DateTimeImmutable;
use Throwable;

use function count;

/**
 * Mutable container holding the runtime state of a single Step run.
 *
 * Counters are updated by the framework as items are read, processed and written. Persistent
 * state needed for restart lives in {@see ExecutionContext}.
 */
final class StepExecution
{
    private const int MAX_FAILURE_EXCEPTIONS = 64;

    private const int MAX_SKIPPED_ITEMS = 10_000;

    private int $commitCount = 0;

    private ?DateTimeImmutable $endTime = null;

    private ExecutionContext $executionContext;

    private ExitStatus $exitStatus;

    /** @var list<Throwable> */
    private array $failureExceptions = [];

    private int $filterCount = 0;

    private ?int $id;

    private ?DateTimeImmutable $lastUpdated = null;

    private int $processSkipCount = 0;

    private int $readCount = 0;

    private int $readSkipCount = 0;

    private int $rollbackCount = 0;

    /** @var list<mixed> */
    private array $skippedItems = [];

    private ?DateTimeImmutable $startTime = null;

    private BatchStatus $status = BatchStatus::STARTING;

    private bool $terminateOnly = false;

    private int $version = 0;

    private int $writeCount = 0;

    private int $writeSkipCount = 0;

    public function __construct(
        private readonly string $stepName,
        private readonly JobExecution $jobExecution,
        ?int $id = null,
    ) {
        $this->id = $id;
        $this->exitStatus = ExitStatus::$EXECUTING;
        $this->executionContext = new ExecutionContext();
        $jobExecution->addStepExecution($this);
    }

    // ── Failures ───────────────────────────────────────────────────────────

    public function addFailureException(Throwable $t): void
    {
        if (count($this->failureExceptions) >= self::MAX_FAILURE_EXCEPTIONS) {
            array_shift($this->failureExceptions);
        }
        $this->failureExceptions[] = $t;
    }

    public function addSkippedItem(mixed $item): void
    {
        if (count($this->skippedItems) >= self::MAX_SKIPPED_ITEMS) {
            array_shift($this->skippedItems);
        }
        $this->skippedItems[] = $item;
    }

    public function getCommitCount(): int
    {
        return $this->commitCount;
    }

    public function getEndTime(): ?DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getExecutionContext(): ExecutionContext
    {
        return $this->executionContext;
    }

    public function getExitStatus(): ExitStatus
    {
        return $this->exitStatus;
    }

    /**
     * @return list<Throwable>
     */
    public function getFailureExceptions(): array
    {
        return $this->failureExceptions;
    }

    public function getFilterCount(): int
    {
        return $this->filterCount;
    }

    // ── Getters / Setters ──────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobExecution(): JobExecution
    {
        return $this->jobExecution;
    }

    public function getLastUpdated(): ?DateTimeImmutable
    {
        return $this->lastUpdated;
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

    public function getRollbackCount(): int
    {
        return $this->rollbackCount;
    }

    public function getSkipCount(): int
    {
        return $this->readSkipCount + $this->processSkipCount + $this->writeSkipCount;
    }

    /**
     * @return list<mixed>
     */
    public function getSkippedItems(): array
    {
        return $this->skippedItems;
    }

    public function getStartTime(): ?DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getStatus(): BatchStatus
    {
        return $this->status;
    }

    public function getStepName(): string
    {
        return $this->stepName;
    }

    public function getSummary(): string
    {
        return sprintf(
            'StepExecution[name=%s, status=%s, exitStatus=%s, readCount=%d, filterCount=%d, writeCount=%d, '
            .'commitCount=%d, rollbackCount=%d, readSkipCount=%d, processSkipCount=%d, writeSkipCount=%d]',
            $this->stepName,
            $this->status->value,
            (string) $this->exitStatus,
            $this->readCount,
            $this->filterCount,
            $this->writeCount,
            $this->commitCount,
            $this->rollbackCount,
            $this->readSkipCount,
            $this->processSkipCount,
            $this->writeSkipCount,
        );
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getWriteCount(): int
    {
        return $this->writeCount;
    }

    public function getWriteSkipCount(): int
    {
        return $this->writeSkipCount;
    }

    public function incrementCommitCount(): void
    {
        ++$this->commitCount;
    }

    public function incrementFilterCount(int $by = 1): void
    {
        $this->filterCount += $by;
    }

    public function incrementProcessSkipCount(int $by = 1): void
    {
        $this->processSkipCount += $by;
    }

    // ── Counters ───────────────────────────────────────────────────────────

    public function incrementReadCount(int $by = 1): void
    {
        $this->readCount += $by;
    }

    public function incrementReadSkipCount(int $by = 1): void
    {
        $this->readSkipCount += $by;
    }

    public function incrementRollbackCount(): void
    {
        ++$this->rollbackCount;
    }

    public function incrementVersion(): void
    {
        ++$this->version;
    }

    public function incrementWriteCount(int $by = 1): void
    {
        $this->writeCount += $by;
    }

    public function incrementWriteSkipCount(int $by = 1): void
    {
        $this->writeSkipCount += $by;
    }

    public function isTerminateOnly(): bool
    {
        return $this->terminateOnly;
    }

    public function setCommitCount(int $value): void
    {
        $this->commitCount = $value;
    }

    public function setEndTime(?DateTimeImmutable $time): void
    {
        $this->endTime = $time;
    }

    public function setExecutionContext(ExecutionContext $context): void
    {
        $this->executionContext = $context;
    }

    public function setExitStatus(ExitStatus $exitStatus): void
    {
        $this->exitStatus = $exitStatus;
    }

    public function setFilterCount(int $value): void
    {
        $this->filterCount = $value;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setLastUpdated(?DateTimeImmutable $time): void
    {
        $this->lastUpdated = $time;
    }

    public function setProcessSkipCount(int $value): void
    {
        $this->processSkipCount = $value;
    }

    public function setReadCount(int $value): void
    {
        $this->readCount = $value;
    }

    public function setReadSkipCount(int $value): void
    {
        $this->readSkipCount = $value;
    }

    public function setRollbackCount(int $value): void
    {
        $this->rollbackCount = $value;
    }

    public function setStartTime(?DateTimeImmutable $time): void
    {
        $this->startTime = $time;
    }

    public function setStatus(BatchStatus $status): void
    {
        $this->status = $status;
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────

    public function setTerminateOnly(): void
    {
        $this->terminateOnly = true;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function setWriteCount(int $value): void
    {
        $this->writeCount = $value;
    }

    public function setWriteSkipCount(int $value): void
    {
        $this->writeSkipCount = $value;
    }

    public function upgradeStatus(BatchStatus $status): void
    {
        $this->status = $this->status->upgradeTo($status);
    }
}
