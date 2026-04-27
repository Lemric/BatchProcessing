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
 * PostgreSQL incrementer using a real {@code SEQUENCE}.
 */
final class PostgresSequenceMaxValueIncrementer implements DataFieldMaxValueIncrementerInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $sequenceName,
    ) {
        SqlIdentifierValidator::assertValidPostgresSequenceName($this->sequenceName);
    }

    public function nextLongValue(): int
    {
        try {
            $literal = str_replace("'", "''", $this->sequenceName);
            $stmt = $this->pdo->query(sprintf("SELECT nextval('%s')", $literal));
            if (false === $stmt) {
                throw new RepositoryException("Postgres sequence query failed for {$this->sequenceName}.");
            }
            $value = $stmt->fetchColumn();

            return is_numeric($value) ? (int) $value : 0;
        } catch (PDOException $e) {
            throw RepositoryException::fromPdo('Postgres incrementer operation', $e);
        }
    }
}
