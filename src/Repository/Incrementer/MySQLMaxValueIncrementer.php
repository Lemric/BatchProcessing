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
 * MySQL-compatible incrementer using the {@code LAST_INSERT_ID(@val := @val + 1)} idiom on a
 * single-row sequence table. Safe against concurrent calls because of the per-connection
 * {@code LAST_INSERT_ID()} session variable.
 */
final class MySQLMaxValueIncrementer implements DataFieldMaxValueIncrementerInterface
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
            $this->pdo->exec(sprintf(
                'UPDATE %s SET %s = LAST_INSERT_ID(%s + 1)',
                $this->sequenceTable,
                $this->columnName,
                $this->columnName,
            ));
            $value = $this->pdo->lastInsertId();
            if (false === $value || '' === $value) {
                // Bootstrap row if missing.
                $this->pdo->exec(sprintf('INSERT INTO %s (%s) VALUES (1)', $this->sequenceTable, $this->columnName));
                $value = $this->pdo->lastInsertId();
            }

            return (int) $value;
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('MySQL incrementer operation', $e);
        }
    }
}
