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

namespace Lemric\BatchProcessing\Bridge\Symfony\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Lemric\BatchProcessing\Repository\PdoJobRepositorySchema;

/**
 * Formal Doctrine migration creating the batch processing metadata schema.
 *
 * Optional class — only useful when {@code doctrine/migrations} is installed in the host
 * application. Excluded from PHPStan analysis (see phpstan.neon.dist) because doctrine/migrations
 * is not a hard dependency of the library.
 *
 * Uses the DDL from {@see PdoJobRepositorySchema}.
 */
final class Version20250101000000CreateBatchProcessingTables extends AbstractMigration
{
    public function down(Schema $schema): void
    {
        $prefix = 'batch_';
        $tables = [
            'job_execution_context',
            'step_execution_context',
            'step_execution',
            'job_execution_params',
            'job_execution',
            'job_instance',
        ];
        foreach ($tables as $table) {
            $this->addSql("DROP TABLE IF EXISTS {$prefix}{$table}");
        }
    }

    public function getDescription(): string
    {
        return 'Create batch processing metadata tables (job_instance, job_execution, step_execution, etc.)';
    }

    public function up(Schema $schema): void
    {
        $platformClass = $this->connection->getDatabasePlatform()::class;
        $driver = match (true) {
            str_contains($platformClass, 'MySQL'), str_contains($platformClass, 'MariaDB') => 'mysql',
            str_contains($platformClass, 'PostgreSQL') => 'pgsql',
            str_contains($platformClass, 'SQLite') => 'sqlite',
            default => 'mysql',
        };

        foreach (PdoJobRepositorySchema::sqlForPlatform($driver) as $sql) {
            $this->addSql($sql);
        }
    }
}
