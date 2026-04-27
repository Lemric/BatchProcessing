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

/**
 * Standard SQL transaction isolation levels. Used by {@see PdoJobRepository} to bracket the
 * creation of a {@see \Lemric\BatchProcessing\Domain\JobExecution} (default: SERIALIZABLE).
 */
enum IsolationLevel: string
{
    case READ_COMMITTED = 'READ COMMITTED';

    case READ_UNCOMMITTED = 'READ UNCOMMITTED';

    case REPEATABLE_READ = 'REPEATABLE READ';

    case SERIALIZABLE = 'SERIALIZABLE';

    /**
     * Returns the dialect-specific {@code SET TRANSACTION ISOLATION LEVEL ...} statement.
     * SQLite does not support per-transaction isolation levels and is treated as a no-op.
     *
     * @return list<string> empty list when no statements are needed
     */
    public function statementsForDriver(string $driver): array
    {
        return match ($driver) {
            'mysql' => ["SET TRANSACTION ISOLATION LEVEL {$this->value}"],
            'pgsql', 'postgres', 'postgresql' => ["SET TRANSACTION ISOLATION LEVEL {$this->value}"],
            default => [],
        };
    }
}
