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

namespace Lemric\BatchProcessing\Repository\Incrementer;

use InvalidArgumentException;
use PDO;

use function is_string;

/**
 * Builds the appropriate {@see DataFieldMaxValueIncrementerInterface} for the given PDO
 * driver. Sequence/table naming follows the convention {@code <prefix><logical>_seq}.
 */
final class IncrementerFactory
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tablePrefix = 'batch_',
    ) {
    }

    /**
     * @param 'job_instance'|'job_execution'|'step_execution' $logical
     */
    public function getIncrementer(string $logical): DataFieldMaxValueIncrementerInterface
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver)) {
            throw new InvalidArgumentException('Could not determine PDO driver name.');
        }

        $sequence = $this->tablePrefix.$logical.'_seq';

        return match ($driver) {
            'mysql' => new MySQLMaxValueIncrementer($this->pdo, $sequence),
            'pgsql', 'postgres', 'postgresql' => new PostgresSequenceMaxValueIncrementer($this->pdo, $sequence),
            'sqlite' => new SqliteMaxValueIncrementer($this->pdo, $sequence),
            default => throw new InvalidArgumentException("No incrementer for driver: {$driver}"),
        };
    }
}
