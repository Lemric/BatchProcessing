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

use InvalidArgumentException;
use PDO;

use function is_string;

/**
 * DDL bundled with the library. Returns the platform-specific SQL statements that create the
 * full metadata schema. Suitable for bootstrapping (development, tests) but in production it is
 * recommended to manage the schema with a real migrations tool (Doctrine Migrations / Laravel
 * migrations) using these statements as a template.
 */
final class PdoJobRepositorySchema
{
    /**
     * Returns the dialect-specific DROP TABLE statements in FK-safe order. Useful for tests
     * and dev rebuilds; not intended for production schema teardown.
     *
     * @return list<string>
     */
    public static function dropSqlForPdo(PDO $pdo, string $prefix = 'batch_'): array
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver)) {
            throw new InvalidArgumentException('Could not determine PDO driver name.');
        }

        return self::dropSqlForPlatform($driver, $prefix);
    }

    /**
     * @return list<string>
     */
    public static function dropSqlForPlatform(string $platform, string $prefix = 'batch_'): array
    {
        // Order matters: child tables first to satisfy FK constraints.
        $tables = [
            'step_execution_context',
            'job_execution_context',
            'step_execution',
            'job_execution_params',
            'job_execution',
            'job_instance',
        ];

        return match ($platform) {
            'mysql' => array_merge(
                ['SET FOREIGN_KEY_CHECKS=0'],
                array_map(static fn (string $t): string => "DROP TABLE IF EXISTS {$prefix}{$t}", $tables),
                ['SET FOREIGN_KEY_CHECKS=1'],
            ),
            'pgsql', 'postgres', 'postgresql' => array_map(
                static fn (string $t): string => "DROP TABLE IF EXISTS {$prefix}{$t} CASCADE",
                $tables,
            ),
            'sqlite' => array_map(
                static fn (string $t): string => "DROP TABLE IF EXISTS {$prefix}{$t}",
                $tables,
            ),
            default => throw new InvalidArgumentException("Unsupported platform: {$platform}"),
        };
    }

    /**
     * @return list<string>
     */
    public static function sqlForPdo(PDO $pdo, string $prefix = 'batch_'): array
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver)) {
            throw new InvalidArgumentException('Could not determine PDO driver name.');
        }

        return self::sqlForPlatform($driver, $prefix);
    }

    /**
     * @return list<string> a list of executable DDL statements for the requested driver
     */
    public static function sqlForPlatform(string $platform, string $prefix = 'batch_'): array
    {
        return match ($platform) {
            'sqlite' => self::sqliteDdl($prefix),
            'mysql' => self::mysqlDdl($prefix),
            'pgsql', 'postgres', 'postgresql' => self::pgsqlDdl($prefix),
            default => throw new InvalidArgumentException("Unsupported platform: {$platform}"),
        };
    }

    /**
     * @return list<string>
     */
    private static function mysqlDdl(string $p): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS {$p}job_instance (
                job_instance_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                version BIGINT NOT NULL DEFAULT 0,
                job_name VARCHAR(100) NOT NULL,
                job_key VARCHAR(2500) NOT NULL,
                CONSTRAINT job_inst_un UNIQUE (job_name, job_key(255))
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution (
                job_execution_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                version BIGINT NOT NULL DEFAULT 0,
                job_instance_id BIGINT UNSIGNED NOT NULL,
                create_time DATETIME(6) NOT NULL,
                start_time DATETIME(6) NULL,
                end_time DATETIME(6) NULL,
                status VARCHAR(10) NOT NULL,
                exit_code VARCHAR(2500) NOT NULL,
                exit_message TEXT NULL,
                last_updated DATETIME(6) NULL,
                CONSTRAINT {$p}job_exec_instance_fk FOREIGN KEY (job_instance_id) REFERENCES {$p}job_instance(job_instance_id)
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution_params (
                job_execution_id BIGINT UNSIGNED NOT NULL,
                param_name VARCHAR(100) NOT NULL,
                param_type VARCHAR(100) NOT NULL,
                param_value VARCHAR(2500) NULL,
                identifying CHAR(1) NOT NULL,
                CONSTRAINT {$p}job_exec_params_fk FOREIGN KEY (job_execution_id) REFERENCES {$p}job_execution(job_execution_id)
            )",
            "CREATE TABLE IF NOT EXISTS {$p}step_execution (
                step_execution_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                version BIGINT NOT NULL DEFAULT 0,
                step_name VARCHAR(100) NOT NULL,
                job_execution_id BIGINT UNSIGNED NOT NULL,
                create_time DATETIME(6) NOT NULL,
                start_time DATETIME(6) NULL,
                end_time DATETIME(6) NULL,
                status VARCHAR(10) NOT NULL,
                commit_count BIGINT NULL,
                read_count BIGINT NULL,
                filter_count BIGINT NULL,
                write_count BIGINT NULL,
                read_skip_count BIGINT NULL,
                write_skip_count BIGINT NULL,
                process_skip_count BIGINT NULL,
                rollback_count BIGINT NULL,
                exit_code VARCHAR(2500) NOT NULL,
                exit_message TEXT NULL,
                last_updated DATETIME(6) NULL,
                CONSTRAINT {$p}step_exec_job_fk FOREIGN KEY (job_execution_id) REFERENCES {$p}job_execution(job_execution_id)
            )",
            "CREATE TABLE IF NOT EXISTS {$p}step_execution_context (
                step_execution_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                short_context VARCHAR(2500) NOT NULL,
                serialized_context LONGTEXT NULL,
                CONSTRAINT {$p}step_exec_ctx_fk FOREIGN KEY (step_execution_id) REFERENCES {$p}step_execution(step_execution_id)
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution_context (
                job_execution_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                short_context VARCHAR(2500) NOT NULL,
                serialized_context LONGTEXT NULL,
                CONSTRAINT {$p}job_exec_ctx_fk FOREIGN KEY (job_execution_id) REFERENCES {$p}job_execution(job_execution_id)
            )",
            "CREATE INDEX idx_{$p}job_inst_name ON {$p}job_instance(job_name)",
            "CREATE INDEX idx_{$p}job_exec_status ON {$p}job_execution(status)",
            "CREATE INDEX idx_{$p}step_exec_job ON {$p}step_execution(job_execution_id)",
            "CREATE INDEX idx_{$p}step_exec_name ON {$p}step_execution(step_name)",
        ];
    }

    /**
     * @return list<string>
     */
    private static function pgsqlDdl(string $p): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS {$p}job_instance (
                job_instance_id BIGSERIAL PRIMARY KEY,
                version BIGINT NOT NULL DEFAULT 0,
                job_name VARCHAR(100) NOT NULL,
                job_key VARCHAR(2500) NOT NULL,
                CONSTRAINT {$p}job_inst_un UNIQUE (job_name, job_key)
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution (
                job_execution_id BIGSERIAL PRIMARY KEY,
                version BIGINT NOT NULL DEFAULT 0,
                job_instance_id BIGINT NOT NULL REFERENCES {$p}job_instance(job_instance_id),
                create_time TIMESTAMP(6) NOT NULL,
                start_time TIMESTAMP(6) NULL,
                end_time TIMESTAMP(6) NULL,
                status VARCHAR(10) NOT NULL,
                exit_code VARCHAR(2500) NOT NULL,
                exit_message TEXT NULL,
                last_updated TIMESTAMP(6) NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution_params (
                job_execution_id BIGINT NOT NULL REFERENCES {$p}job_execution(job_execution_id),
                param_name VARCHAR(100) NOT NULL,
                param_type VARCHAR(100) NOT NULL,
                param_value VARCHAR(2500) NULL,
                identifying CHAR(1) NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$p}step_execution (
                step_execution_id BIGSERIAL PRIMARY KEY,
                version BIGINT NOT NULL DEFAULT 0,
                step_name VARCHAR(100) NOT NULL,
                job_execution_id BIGINT NOT NULL REFERENCES {$p}job_execution(job_execution_id),
                create_time TIMESTAMP(6) NOT NULL,
                start_time TIMESTAMP(6) NULL,
                end_time TIMESTAMP(6) NULL,
                status VARCHAR(10) NOT NULL,
                commit_count BIGINT NULL,
                read_count BIGINT NULL,
                filter_count BIGINT NULL,
                write_count BIGINT NULL,
                read_skip_count BIGINT NULL,
                write_skip_count BIGINT NULL,
                process_skip_count BIGINT NULL,
                rollback_count BIGINT NULL,
                exit_code VARCHAR(2500) NOT NULL,
                exit_message TEXT NULL,
                last_updated TIMESTAMP(6) NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$p}step_execution_context (
                step_execution_id BIGINT PRIMARY KEY REFERENCES {$p}step_execution(step_execution_id),
                short_context VARCHAR(2500) NOT NULL,
                serialized_context TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution_context (
                job_execution_id BIGINT PRIMARY KEY REFERENCES {$p}job_execution(job_execution_id),
                short_context VARCHAR(2500) NOT NULL,
                serialized_context TEXT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_{$p}job_inst_name ON {$p}job_instance(job_name)",
            "CREATE INDEX IF NOT EXISTS idx_{$p}job_exec_status ON {$p}job_execution(status)",
            "CREATE INDEX IF NOT EXISTS idx_{$p}step_exec_job ON {$p}step_execution(job_execution_id)",
            "CREATE INDEX IF NOT EXISTS idx_{$p}step_exec_name ON {$p}step_execution(step_name)",
        ];
    }

    /**
     * @return list<string>
     */
    private static function sqliteDdl(string $p): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS {$p}job_instance (
                job_instance_id INTEGER PRIMARY KEY AUTOINCREMENT,
                version INTEGER NOT NULL DEFAULT 0,
                job_name TEXT NOT NULL,
                job_key TEXT NOT NULL,
                UNIQUE (job_name, job_key)
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution (
                job_execution_id INTEGER PRIMARY KEY AUTOINCREMENT,
                version INTEGER NOT NULL DEFAULT 0,
                job_instance_id INTEGER NOT NULL REFERENCES {$p}job_instance(job_instance_id),
                create_time TEXT NOT NULL,
                start_time TEXT NULL,
                end_time TEXT NULL,
                status TEXT NOT NULL,
                exit_code TEXT NOT NULL,
                exit_message TEXT NULL,
                last_updated TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution_params (
                job_execution_id INTEGER NOT NULL REFERENCES {$p}job_execution(job_execution_id),
                param_name TEXT NOT NULL,
                param_type TEXT NOT NULL,
                param_value TEXT NULL,
                identifying TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$p}step_execution (
                step_execution_id INTEGER PRIMARY KEY AUTOINCREMENT,
                version INTEGER NOT NULL DEFAULT 0,
                step_name TEXT NOT NULL,
                job_execution_id INTEGER NOT NULL REFERENCES {$p}job_execution(job_execution_id),
                create_time TEXT NOT NULL,
                start_time TEXT NULL,
                end_time TEXT NULL,
                status TEXT NOT NULL,
                commit_count INTEGER NULL,
                read_count INTEGER NULL,
                filter_count INTEGER NULL,
                write_count INTEGER NULL,
                read_skip_count INTEGER NULL,
                write_skip_count INTEGER NULL,
                process_skip_count INTEGER NULL,
                rollback_count INTEGER NULL,
                exit_code TEXT NOT NULL,
                exit_message TEXT NULL,
                last_updated TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$p}step_execution_context (
                step_execution_id INTEGER PRIMARY KEY REFERENCES {$p}step_execution(step_execution_id),
                short_context TEXT NOT NULL,
                serialized_context TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$p}job_execution_context (
                job_execution_id INTEGER PRIMARY KEY REFERENCES {$p}job_execution(job_execution_id),
                short_context TEXT NOT NULL,
                serialized_context TEXT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_{$p}job_inst_name ON {$p}job_instance(job_name)",
            "CREATE INDEX IF NOT EXISTS idx_{$p}job_exec_status ON {$p}job_execution(status)",
            "CREATE INDEX IF NOT EXISTS idx_{$p}step_exec_job ON {$p}step_execution(job_execution_id)",
            "CREATE INDEX IF NOT EXISTS idx_{$p}step_exec_name ON {$p}step_execution(step_name)",
        ];
    }
}
