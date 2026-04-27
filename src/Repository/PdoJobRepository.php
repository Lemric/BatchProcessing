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

namespace Lemric\BatchProcessing\Repository;

use DateTimeImmutable;
use Exception;
use JsonException;
use Lemric\BatchProcessing\Domain\{BatchStatus, ExecutionContext, ExitStatus, JobExecution, JobInstance, JobParameter, JobParameters, StepExecution};
use Lemric\BatchProcessing\Exception\{OptimisticLockingFailureException, RepositoryException};
use Lemric\BatchProcessing\Security\{SensitiveDataSanitizer, SqlIdentifierValidator};
use PDO;
use PDOException;
use PDOStatement;

use Stringable;
use Throwable;

use function is_string;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Production PDO-backed repository. Compatible with MySQL 8+, PostgreSQL 14+ and SQLite 3.37+.
 *
 * Use {@see PdoJobRepositorySchema::sqlForPlatform()} to obtain the matching DDL.
 */
final class PdoJobRepository extends AbstractJobRepository
{
    private const int SHORT_CONTEXT_MAX = 2500;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tablePrefix = 'batch_',
        private readonly IsolationLevel $isolationLevelForCreate = IsolationLevel::SERIALIZABLE,
    ) {
        SqlIdentifierValidator::assertValidTablePrefix($this->tablePrefix);
    }

    // ── Step Execution ────────────────────────────────────────────────────

    public function add(StepExecution $stepExecution): void
    {
        $jobExecution = $stepExecution->getJobExecution();
        if (null === $jobExecution->getId()) {
            throw new RepositoryException('Cannot persist StepExecution before its JobExecution.');
        }
        $now = new DateTimeImmutable();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table('step_execution')}
                 (version, step_name, job_execution_id, create_time, status, exit_code, exit_message, last_updated)
                 VALUES (0, :name, :job, :create, :status, :exitCode, :exitMessage, :lastUpdated)",
            );
            $stmt->execute([
                'name' => $stepExecution->getStepName(),
                'job' => $jobExecution->getId(),
                'create' => $this->formatDate($now),
                'status' => $stepExecution->getStatus()->value,
                'exitCode' => $stepExecution->getExitStatus()->getExitCode(),
                'exitMessage' => $this->sanitizeExitText($stepExecution->getExitStatus()->getExitDescription()),
                'lastUpdated' => $this->formatDate($now),
            ]);
            $stepExecution->setId(self::asInt($this->pdo->lastInsertId()));
            $stepExecution->setLastUpdated($now);
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('Failed to add step execution', $e);
        }
    }

    // ── Job Execution ─────────────────────────────────────────────────────

    public function createJobExecution(JobInstance $instance, JobParameters $parameters): JobExecution
    {
        $now = new DateTimeImmutable();
        try {
            $this->applyCreateIsolation();
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table('job_execution')}
                 (version, job_instance_id, create_time, status, exit_code, exit_message, last_updated)
                 VALUES (0, :instance, :create, :status, :exitCode, :exitMessage, :lastUpdated)",
            );
            $stmt->execute([
                'instance' => $instance->getId(),
                'create' => $this->formatDate($now),
                'status' => BatchStatus::STARTING->value,
                'exitCode' => ExitStatus::UNKNOWN_CODE,
                'exitMessage' => '',
                'lastUpdated' => $this->formatDate($now),
            ]);
            $id = self::asInt($this->pdo->lastInsertId());

            $execution = new JobExecution($id, $instance, $parameters);
            $execution->setCreateTime($now);
            $execution->setLastUpdated($now);

            $this->insertParameters($id, $parameters);
            $this->pdo->commit();

            return $execution;
        } catch (RepositoryException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw RepositoryException::fromPdo('Failed to create job execution', $e);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // ── Job Instance ──────────────────────────────────────────────────────

    public function createJobInstance(string $jobName, JobParameters $parameters): JobInstance
    {
        $existing = $this->getJobInstanceByJobNameAndParameters($jobName, $parameters);
        if (null !== $existing) {
            return $existing;
        }
        $key = $parameters->toJobKey();
        try {
            $this->applyCreateIsolation();
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table('job_instance')} (version, job_name, job_key) VALUES (0, :name, :key)",
            );
            $stmt->execute(['name' => $jobName, 'key' => $key]);
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('Failed to create job instance', $e);
        }
        $id = self::asInt($this->pdo->lastInsertId());

        return new JobInstance($id, $jobName, $key);
    }

    public function deleteJobExecution(int $executionId): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownTransaction) {
                $this->pdo->beginTransaction();
            }
            // Delete step execution contexts.
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->table('step_execution_context')}
                 WHERE step_execution_id IN (
                     SELECT step_execution_id FROM {$this->table('step_execution')} WHERE job_execution_id = :id
                 )",
            );
            $stmt->execute(['id' => $executionId]);

            // Delete step executions.
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->table('step_execution')} WHERE job_execution_id = :id",
            );
            $stmt->execute(['id' => $executionId]);

            // Delete job execution context.
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->table('job_execution_context')} WHERE job_execution_id = :id",
            );
            $stmt->execute(['id' => $executionId]);

            // Delete job execution parameters.
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->table('job_execution_params')} WHERE job_execution_id = :id",
            );
            $stmt->execute(['id' => $executionId]);

            // Delete the job execution itself.
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->table('job_execution')} WHERE job_execution_id = :id",
            );
            $stmt->execute(['id' => $executionId]);
            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (PDOException $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw RepositoryException::fromPdo('Failed to delete job execution', $e);
        }
    }

    public function deleteJobInstance(int $instanceId): void
    {
        try {
            $this->pdo->beginTransaction();
            // Find all associated job execution ids.
            $stmt = $this->pdo->prepare(
                "SELECT job_execution_id FROM {$this->table('job_execution')} WHERE job_instance_id = :id",
            );
            $stmt->execute(['id' => $instanceId]);
            $executionIds = $this->fetchAllIds($stmt);

            // Delete each execution (cascading into steps, contexts, params).
            foreach ($executionIds as $executionId) {
                $this->deleteJobExecution($executionId);
            }

            // Delete the instance itself.
            $stmt = $this->pdo->prepare(
                "DELETE FROM {$this->table('job_instance')} WHERE job_instance_id = :id",
            );
            $stmt->execute(['id' => $instanceId]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw RepositoryException::fromPdo('Failed to delete job instance', $e);
        }
    }

    public function findJobExecutions(JobInstance $instance): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT job_execution_id FROM {$this->table('job_execution')}
             WHERE job_instance_id = :id ORDER BY job_execution_id DESC",
        );
        $stmt->execute(['id' => $instance->getId()]);
        $list = [];
        foreach ($this->fetchAllIds($stmt) as $id) {
            $execution = $this->getJobExecution($id);
            if (null !== $execution) {
                $list[] = $execution;
            }
        }

        return $list;
    }

    public function findJobInstancesByName(string $jobName, int $start = 0, int $count = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT job_instance_id, version, job_name, job_key FROM {$this->table('job_instance')}
             WHERE job_name = :name ORDER BY job_instance_id DESC LIMIT :limit OFFSET :offset",
        );
        $stmt->bindValue('name', $jobName);
        $stmt->bindValue('limit', $count, PDO::PARAM_INT);
        $stmt->bindValue('offset', $start, PDO::PARAM_INT);
        $stmt->execute();
        $list = [];
        foreach ($this->fetchAllAssoc($stmt) as $row) {
            $list[] = $this->mapJobInstance($row);
        }

        return $list;
    }

    public function findRunningJobExecutions(string $jobName): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT je.job_execution_id FROM {$this->table('job_execution')} je
             JOIN {$this->table('job_instance')} ji ON je.job_instance_id = ji.job_instance_id
             WHERE ji.job_name = :name AND je.status IN ('STARTING', 'STARTED', 'STOPPING')",
        );
        $stmt->execute(['name' => $jobName]);
        $list = [];
        foreach ($this->fetchAllIds($stmt) as $id) {
            $execution = $this->getJobExecution($id);
            if (null !== $execution) {
                $list[] = $execution;
            }
        }

        return $list;
    }

    public function getJobExecution(int $executionId): ?JobExecution
    {
        $stmt = $this->pdo->prepare(
            "SELECT je.*, ji.job_name, ji.job_key, ji.version AS instance_version
             FROM {$this->table('job_execution')} je
             JOIN {$this->table('job_instance')} ji ON je.job_instance_id = ji.job_instance_id
             WHERE je.job_execution_id = :id",
        );
        $stmt->execute(['id' => $executionId]);
        $row = $this->fetchOne($stmt);
        if (null === $row) {
            return null;
        }
        $instance = new JobInstance(
            self::asInt($row['job_instance_id']),
            self::asString($row['job_name']),
            self::asString($row['job_key']),
            self::asInt($row['instance_version'] ?? 0),
        );
        $parameters = $this->loadParameters($executionId);
        $execution = new JobExecution($executionId, $instance, $parameters);
        $this->hydrateJobExecution($execution, $row);
        $execution->setExecutionContext($this->loadContext(
            $this->table('job_execution_context'),
            'job_execution_id',
            $executionId,
        ));

        return $execution;
    }

    public function getJobInstance(int $instanceId): ?JobInstance
    {
        $stmt = $this->pdo->prepare(
            "SELECT job_instance_id, version, job_name, job_key FROM {$this->table('job_instance')} WHERE job_instance_id = :id",
        );
        $stmt->execute(['id' => $instanceId]);
        $row = $this->fetchOne($stmt);

        return null === $row ? null : $this->mapJobInstance($row);
    }

    public function getJobInstanceByJobNameAndParameters(string $jobName, JobParameters $parameters): ?JobInstance
    {
        $stmt = $this->pdo->prepare(
            "SELECT job_instance_id, version, job_name, job_key FROM {$this->table('job_instance')}
             WHERE job_name = :name AND job_key = :key",
        );
        $stmt->execute(['name' => $jobName, 'key' => $parameters->toJobKey()]);
        $row = $this->fetchOne($stmt);

        return null === $row ? null : $this->mapJobInstance($row);
    }

    public function getJobNames(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT job_name FROM {$this->table('job_instance')} ORDER BY job_name",
        );
        if (false === $stmt) {
            return [];
        }
        /** @var list<string> $names */
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $names;
    }

    public function getLastStepExecution(JobInstance $instance, string $stepName): ?StepExecution
    {
        $stmt = $this->pdo->prepare(
            "SELECT se.* FROM {$this->table('step_execution')} se
             JOIN {$this->table('job_execution')} je ON se.job_execution_id = je.job_execution_id
             WHERE je.job_instance_id = :id AND se.step_name = :name
             ORDER BY se.step_execution_id DESC LIMIT 1",
        );
        $stmt->execute(['id' => $instance->getId(), 'name' => $stepName]);
        $row = $this->fetchOne($stmt);
        if (null === $row) {
            return null;
        }
        $jobExecution = $this->getJobExecution(self::asInt($row['job_execution_id']));
        if (null === $jobExecution) {
            return null;
        }
        $stepId = self::asInt($row['step_execution_id']);
        foreach ($jobExecution->getStepExecutions() as $step) {
            if ($step->getStepName() === $stepName && $step->getId() === $stepId) {
                return $step;
            }
        }

        // Build a detached step execution if it wasn't pulled by the job execution loader.
        $step = new StepExecution($stepName, $jobExecution, $stepId);
        $this->hydrateStepExecution($step, $row);
        $step->setExecutionContext($this->loadContext(
            $this->table('step_execution_context'),
            'step_execution_id',
            $stepId,
        ));

        return $step;
    }

    public function getStepExecutionCount(JobInstance $instance, string $stepName): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table('step_execution')} se
             JOIN {$this->table('job_execution')} je ON se.job_execution_id = je.job_execution_id
             WHERE je.job_instance_id = :id AND se.step_name = :name",
        );
        $stmt->execute(['id' => $instance->getId(), 'name' => $stepName]);

        return self::asInt($stmt->fetchColumn());
    }

    public function isJobInstanceExists(string $jobName, JobParameters $parameters): bool
    {
        return null !== $this->getJobInstanceByJobNameAndParameters($jobName, $parameters);
    }

    public function update(StepExecution $stepExecution): void
    {
        if (null === $stepExecution->getId()) {
            $this->add($stepExecution);

            return;
        }
        $now = new DateTimeImmutable();
        $stepExecution->setLastUpdated($now);
        $currentVersion = $stepExecution->getVersion();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table('step_execution')} SET
                    version = version + 1,
                    start_time = :startTime,
                    end_time = :endTime,
                    status = :status,
                    commit_count = :commitCount,
                    read_count = :readCount,
                    filter_count = :filterCount,
                    write_count = :writeCount,
                    read_skip_count = :readSkipCount,
                    write_skip_count = :writeSkipCount,
                    process_skip_count = :processSkipCount,
                    rollback_count = :rollbackCount,
                    exit_code = :exitCode,
                    exit_message = :exitMessage,
                    last_updated = :lastUpdated
                 WHERE step_execution_id = :id AND version = :version",
            );
            $stmt->execute([
                'startTime' => $this->formatNullableDate($stepExecution->getStartTime()),
                'endTime' => $this->formatNullableDate($stepExecution->getEndTime()),
                'status' => $stepExecution->getStatus()->value,
                'commitCount' => $stepExecution->getCommitCount(),
                'readCount' => $stepExecution->getReadCount(),
                'filterCount' => $stepExecution->getFilterCount(),
                'writeCount' => $stepExecution->getWriteCount(),
                'readSkipCount' => $stepExecution->getReadSkipCount(),
                'writeSkipCount' => $stepExecution->getWriteSkipCount(),
                'processSkipCount' => $stepExecution->getProcessSkipCount(),
                'rollbackCount' => $stepExecution->getRollbackCount(),
                'exitCode' => $stepExecution->getExitStatus()->getExitCode(),
                'exitMessage' => $this->sanitizeExitText($stepExecution->getExitStatus()->getExitDescription()),
                'lastUpdated' => $this->formatDate($now),
                'id' => $stepExecution->getId(),
                'version' => $currentVersion,
            ]);
            if (0 === $stmt->rowCount()) {
                throw new OptimisticLockingFailureException(sprintf('StepExecution (id=%d) was updated concurrently (expected version %d).', $stepExecution->getId(), $currentVersion));
            }
            $stepExecution->incrementVersion();
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('Failed to update step execution', $e);
        }
    }

    public function updateExecutionContext(StepExecution $stepExecution): void
    {
        if (null === $stepExecution->getId()) {
            return;
        }
        $this->upsertContext(
            $this->table('step_execution_context'),
            'step_execution_id',
            $stepExecution->getId(),
            $stepExecution->getExecutionContext(),
        );
        $stepExecution->getExecutionContext()->clearDirtyFlag();
    }

    public function updateJobExecution(JobExecution $jobExecution): void
    {
        if (null === $jobExecution->getId()) {
            throw new RepositoryException('JobExecution must be persisted (have an id) before update.');
        }
        $now = new DateTimeImmutable();
        $jobExecution->setLastUpdated($now);
        $currentVersion = $jobExecution->getVersion();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table('job_execution')} SET
                    version = version + 1,
                    start_time = :startTime,
                    end_time = :endTime,
                    status = :status,
                    exit_code = :exitCode,
                    exit_message = :exitMessage,
                    last_updated = :lastUpdated
                 WHERE job_execution_id = :id AND version = :version",
            );
            $stmt->execute([
                'startTime' => $this->formatNullableDate($jobExecution->getStartTime()),
                'endTime' => $this->formatNullableDate($jobExecution->getEndTime()),
                'status' => $jobExecution->getStatus()->value,
                'exitCode' => $jobExecution->getExitStatus()->getExitCode(),
                'exitMessage' => $this->buildExitMessage($jobExecution),
                'lastUpdated' => $this->formatDate($now),
                'id' => $jobExecution->getId(),
                'version' => $currentVersion,
            ]);
            if (0 === $stmt->rowCount()) {
                throw new OptimisticLockingFailureException(sprintf('JobExecution (id=%d) was updated concurrently (expected version %d).', $jobExecution->getId(), $currentVersion));
            }
            $jobExecution->incrementVersion();
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('Failed to update job execution', $e);
        }
    }

    public function updateJobExecutionContext(JobExecution $jobExecution): void
    {
        if (null === $jobExecution->getId()) {
            return;
        }
        $this->upsertContext(
            $this->table('job_execution_context'),
            'job_execution_id',
            $jobExecution->getId(),
            $jobExecution->getExecutionContext(),
        );
        $jobExecution->getExecutionContext()->clearDirtyFlag();
    }

    /**
     * Issues the configured {@code SET TRANSACTION ISOLATION LEVEL ...} prior to the next
     * statement on the connection. No-op for drivers that do not honour per-tx isolation.
     */
    private function applyCreateIsolation(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver)) {
            return;
        }
        foreach ($this->isolationLevelForCreate->statementsForDriver($driver) as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException) {
                // Some hosted MySQLs deny SET TRANSACTION when not inside a tx; ignore best-effort.
            }
        }
    }

    private static function asFloat(mixed $value, float $default = 0.0): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    private static function asInt(mixed $value, int $default = 0): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private static function asString(mixed $value, string $default = ''): string
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return $default;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Builds the exit_message field, including serialized failure exceptions when present.
     */
    private function buildExitMessage(JobExecution $jobExecution): string
    {
        $description = $jobExecution->getExitStatus()->getExitDescription();
        $exceptions = $jobExecution->getFailureExceptions();
        if ([] === $exceptions) {
            return $description;
        }

        $parts = ['' !== $description ? $description : null];
        foreach ($exceptions as $e) {
            $parts[] = SensitiveDataSanitizer::sanitize(get_class($e).': '.$e->getMessage());
        }

        return $this->sanitizeExitText(implode("\n", array_filter($parts)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllAssoc(PDOStatement $stmt): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function fetchAllIds(PDOStatement $stmt): array
    {
        /** @var list<int|string> $raw */
        $raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $ids = [];
        foreach ($raw as $value) {
            $ids[] = self::asInt($value);
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOne(PDOStatement $stmt): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return false === $row ? null : $row;
    }

    private function formatDate(DateTimeImmutable $d): string
    {
        return $d->format('Y-m-d H:i:s.u');
    }

    private function formatNullableDate(?DateTimeImmutable $d): ?string
    {
        return null === $d ? null : $this->formatDate($d);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateJobExecution(JobExecution $execution, array $row): void
    {
        $execution->setStatus(BatchStatus::from(self::asString($row['status'] ?? '')));
        $execution->setExitStatus(new ExitStatus(self::asString($row['exit_code'] ?? ''), self::asString($row['exit_message'] ?? '')));
        $execution->setCreateTime($this->parseDate($row['create_time'] ?? null));
        $execution->setStartTime($this->parseDate($row['start_time'] ?? null));
        $execution->setEndTime($this->parseDate($row['end_time'] ?? null));
        $execution->setLastUpdated($this->parseDate($row['last_updated'] ?? null));
        $execution->setVersion(self::asInt($row['version'] ?? 0));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateStepExecution(StepExecution $step, array $row): void
    {
        $step->setStatus(BatchStatus::from(self::asString($row['status'] ?? '')));
        $step->setExitStatus(new ExitStatus(self::asString($row['exit_code'] ?? ''), self::asString($row['exit_message'] ?? '')));
        $step->setStartTime($this->parseDate($row['start_time'] ?? null));
        $step->setEndTime($this->parseDate($row['end_time'] ?? null));
        $step->setLastUpdated($this->parseDate($row['last_updated'] ?? null));
        $step->setCommitCount(self::asInt($row['commit_count'] ?? 0));
        $step->setReadCount(self::asInt($row['read_count'] ?? 0));
        $step->setFilterCount(self::asInt($row['filter_count'] ?? 0));
        $step->setWriteCount(self::asInt($row['write_count'] ?? 0));
        $step->setReadSkipCount(self::asInt($row['read_skip_count'] ?? 0));
        $step->setWriteSkipCount(self::asInt($row['write_skip_count'] ?? 0));
        $step->setProcessSkipCount(self::asInt($row['process_skip_count'] ?? 0));
        $step->setRollbackCount(self::asInt($row['rollback_count'] ?? 0));
        $step->setVersion(self::asInt($row['version'] ?? 0));
    }

    private function insertParameters(int $jobExecutionId, JobParameters $parameters): void
    {
        if ($parameters->isEmpty()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table('job_execution_params')}
             (job_execution_id, param_name, param_type, param_value, identifying)
             VALUES (:id, :name, :type, :value, :identifying)",
        );
        foreach ($parameters->getParameters() as $name => $param) {
            $stmt->execute([
                'id' => $jobExecutionId,
                'name' => $name,
                'type' => $param->getType(),
                'value' => $param->valueAsString(),
                'identifying' => $param->isIdentifying() ? 'Y' : 'N',
            ]);
        }
    }

    private function loadContext(string $table, string $idColumn, int $id): ExecutionContext
    {
        $stmt = $this->pdo->prepare("SELECT short_context, serialized_context FROM {$table} WHERE {$idColumn} = :id");
        $stmt->execute(['id' => $id]);
        $row = $this->fetchOne($stmt);
        if (null === $row) {
            return new ExecutionContext();
        }
        $serialized = $row['serialized_context'] ?? null;
        $short = $row['short_context'] ?? '';
        $payload = (null !== $serialized && '' !== $serialized)
            ? self::asString($serialized)
            : self::asString($short);
        if ('' === $payload) {
            return new ExecutionContext();
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RepositoryException('Failed to decode execution context.', previous: $e);
        }

        return ExecutionContext::fromArray($data);
    }

    private function loadParameters(int $jobExecutionId): JobParameters
    {
        $stmt = $this->pdo->prepare(
            "SELECT param_name, param_type, param_value, identifying
             FROM {$this->table('job_execution_params')} WHERE job_execution_id = :id",
        );
        $stmt->execute(['id' => $jobExecutionId]);
        $params = [];
        foreach ($this->fetchAllAssoc($stmt) as $row) {
            $name = self::asString($row['param_name'] ?? '');
            $type = self::asString($row['param_type'] ?? '');
            $value = $row['param_value'] ?? null;
            $identifying = 'Y' === self::asString($row['identifying'] ?? '');
            $params[$name] = match ($type) {
                JobParameter::TYPE_LONG => JobParameter::ofLong($name, null === $value ? null : self::asInt($value), $identifying),
                JobParameter::TYPE_DOUBLE => JobParameter::ofDouble($name, null === $value ? null : self::asFloat($value), $identifying),
                JobParameter::TYPE_DATE => JobParameter::ofDate($name, null === $value ? null : new DateTimeImmutable(self::asString($value)), $identifying),
                default => JobParameter::ofString($name, null === $value ? null : self::asString($value), $identifying),
            };
        }

        return new JobParameters($params);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapJobInstance(array $row): JobInstance
    {
        return new JobInstance(
            self::asInt($row['job_instance_id'] ?? 0),
            self::asString($row['job_name'] ?? ''),
            self::asString($row['job_key'] ?? ''),
            self::asInt($row['version'] ?? 0),
        );
    }

    private function parseDate(mixed $raw): ?DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        try {
            return new DateTimeImmutable(self::asString($raw));
        } catch (Exception) {
            return null;
        }
    }

    private function sanitizeExitText(string $text): string
    {
        return SensitiveDataSanitizer::sanitize($text);
    }

    private function table(string $name): string
    {
        return $this->tablePrefix.$name;
    }

    private function upsertContext(string $table, string $idColumn, int $id, ExecutionContext $ctx): void
    {
        $payload = json_encode($ctx->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $short = mb_strlen($payload) <= self::SHORT_CONTEXT_MAX ? $payload : '';
        $serialized = mb_strlen($payload) > self::SHORT_CONTEXT_MAX ? $payload : null;

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ('mysql' === $driver) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$table} ({$idColumn}, short_context, serialized_context) VALUES (:id, :short, :serialized)
                     ON DUPLICATE KEY UPDATE short_context = VALUES(short_context), serialized_context = VALUES(serialized_context)",
                );
            } elseif ('pgsql' === $driver) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$table} ({$idColumn}, short_context, serialized_context) VALUES (:id, :short, :serialized)
                     ON CONFLICT ({$idColumn}) DO UPDATE SET short_context = EXCLUDED.short_context, serialized_context = EXCLUDED.serialized_context",
                );
            } elseif ('sqlite' === $driver) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$table} ({$idColumn}, short_context, serialized_context) VALUES (:id, :short, :serialized)
                     ON CONFLICT ({$idColumn}) DO UPDATE SET short_context = excluded.short_context, serialized_context = excluded.serialized_context",
                );
            } else {
                $this->upsertContextLegacySelectInsert($table, $idColumn, $id, $short, $serialized);

                return;
            }
            $stmt->execute(['id' => $id, 'short' => $short, 'serialized' => $serialized]);
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('Failed to upsert execution context', $e);
        }
    }

    /**
     * Fallback when the PDO driver does not support native UPSERT (should be rare).
     */
    private function upsertContextLegacySelectInsert(string $table, string $idColumn, int $id, string $short, ?string $serialized): void
    {
        try {
            $exists = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE {$idColumn} = :id");
            $exists->execute(['id' => $id]);
            if (false !== $exists->fetchColumn()) {
                $stmt = $this->pdo->prepare(
                    "UPDATE {$table} SET short_context = :short, serialized_context = :serialized WHERE {$idColumn} = :id",
                );
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$table} ({$idColumn}, short_context, serialized_context) VALUES (:id, :short, :serialized)",
                );
            }
            $stmt->execute(['id' => $id, 'short' => $short, 'serialized' => $serialized]);
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('Failed to upsert execution context', $e);
        }
    }
}
