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
 * Mutable container representing a single execution attempt of a {@see JobInstance}.
 *
 * Aggregates child {@see StepExecution}s and the persistent {@see ExecutionContext} used to
 * support restart semantics.
 */
final class JobExecution
{
    private const int MAX_FAILURE_EXCEPTIONS = 64;

    private ?DateTimeImmutable $createTime;

    private ?DateTimeImmutable $endTime = null;

    private ExecutionContext $executionContext;

    private ExitStatus $exitStatus;

    /** @var list<Throwable> */
    private array $failureExceptions = [];

    private ?int $id;

    private ?DateTimeImmutable $lastUpdated = null;

    private ?DateTimeImmutable $startTime = null;

    private BatchStatus $status = BatchStatus::STARTING;

    /** @var list<StepExecution> */
    private array $stepExecutions = [];

    private int $version = 0;

    public function __construct(
        ?int $id,
        private readonly JobInstance $jobInstance,
        private readonly JobParameters $jobParameters,
    ) {
        $this->id = $id;
        $this->exitStatus = ExitStatus::$UNKNOWN;
        $this->executionContext = new ExecutionContext();
        $this->createTime = new DateTimeImmutable();
    }

    public function addFailureException(Throwable $t): void
    {
        if (count($this->failureExceptions) >= self::MAX_FAILURE_EXCEPTIONS) {
            array_shift($this->failureExceptions);
        }
        $this->failureExceptions[] = $t;
    }

    /**
     * @internal called by {@see StepExecution::__construct()}; do not call directly
     */
    public function addStepExecution(StepExecution $stepExecution): void
    {
        $this->stepExecutions[] = $stepExecution;
    }

    public function createStepExecution(string $stepName): StepExecution
    {
        // The constructor of StepExecution will append itself to $this->stepExecutions
        // through addStepExecution() below.
        return new StepExecution($stepName, $this);
    }

    /**
     * Aggregated list of failure exceptions across the job and all its step executions.
     *
     * @return list<Throwable>
     */
    public function getAllFailureExceptions(): array
    {
        $all = $this->failureExceptions;
        foreach ($this->stepExecutions as $step) {
            foreach ($step->getFailureExceptions() as $t) {
                $all[] = $t;
            }
        }

        return $all;
    }

    public function getCreateTime(): ?DateTimeImmutable
    {
        return $this->createTime;
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

    // ── Getters / Setters ──────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobInstance(): JobInstance
    {
        return $this->jobInstance;
    }

    public function getJobName(): string
    {
        return $this->jobInstance->getJobName();
    }

    public function getJobParameters(): JobParameters
    {
        return $this->jobParameters;
    }

    public function getLastUpdated(): ?DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function getStartTime(): ?DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getStatus(): BatchStatus
    {
        return $this->status;
    }

    /**
     * @return list<StepExecution>
     */
    public function getStepExecutions(): array
    {
        return $this->stepExecutions;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function incrementVersion(): void
    {
        ++$this->version;
    }

    public function isRunning(): bool
    {
        return $this->status->isRunning();
    }

    public function isStopping(): bool
    {
        return BatchStatus::STOPPING === $this->status;
    }

    public function setCreateTime(?DateTimeImmutable $t): void
    {
        $this->createTime = $t;
    }

    public function setEndTime(?DateTimeImmutable $time): void
    {
        $this->endTime = $time;
    }

    public function setExecutionContext(ExecutionContext $ctx): void
    {
        $this->executionContext = $ctx;
    }

    public function setExitStatus(ExitStatus $exitStatus): void
    {
        $this->exitStatus = $exitStatus;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setLastUpdated(?DateTimeImmutable $time): void
    {
        $this->lastUpdated = $time;
    }

    public function setStartTime(?DateTimeImmutable $time): void
    {
        $this->startTime = $time;
    }

    public function setStatus(BatchStatus $status): void
    {
        $this->status = $status;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function stop(): void
    {
        foreach ($this->stepExecutions as $step) {
            $step->setTerminateOnly();
        }
        $this->status = BatchStatus::STOPPING;
    }

    public function upgradeStatus(BatchStatus $newStatus): void
    {
        $this->status = $this->status->upgradeTo($newStatus);
    }
}
