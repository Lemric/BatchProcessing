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

use Lemric\BatchProcessing\Exception\RepositoryException;
use Lemric\BatchProcessing\Security\SqlIdentifierValidator;
use PDO;
use PDOException;

/**
 * SQLite incrementer based on a single-row sequence table updated inside an immediate
 * transaction (SQLite serializes writes globally, providing the ordering guarantee).
 */
final class SqliteMaxValueIncrementer implements DataFieldMaxValueIncrementerInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $sequenceTable,
        private readonly string $columnName = 'id',
    ) {
        SqlIdentifierValidator::assertValidIdentifier($this->sequenceTable, 'table');
        SqlIdentifierValidator::assertValidIdentifier($this->columnName, 'column');
    }

    public function nextLongValue(): int
    {
        try {
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec(sprintf(
                    'UPDATE %s SET %s = %s + 1',
                    $this->sequenceTable,
                    $this->columnName,
                    $this->columnName,
                ));
                $stmt = $this->pdo->query(sprintf('SELECT %s FROM %s LIMIT 1', $this->columnName, $this->sequenceTable));
                if (false === $stmt) {
                    throw new RepositoryException("SQLite sequence read failed for {$this->sequenceTable}.");
                }
                $value = $stmt->fetchColumn();
                if (false === $value) {
                    $this->pdo->exec(sprintf('INSERT INTO %s (%s) VALUES (1)', $this->sequenceTable, $this->columnName));
                    $value = 1;
                }
                $this->pdo->commit();

                return is_numeric($value) ? (int) $value : 0;
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('SQLite incrementer operation', $e);
        }
    }
}
