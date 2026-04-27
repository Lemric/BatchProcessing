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

namespace Lemric\BatchProcessing\Transaction;

use Lemric\BatchProcessing\Exception\TransactionException;
use PDO;
use PDOException;

/**
 * PDO-backed transaction manager. Supports nesting through SAVEPOINTs (where the underlying
 * driver allows them - MySQL, PostgreSQL, SQLite all do).
 */
final class PdoTransactionManager implements TransactionManagerInterface
{
    private int $depth = 0;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function begin(): void
    {
        try {
            if (0 === $this->depth) {
                $this->pdo->beginTransaction();
            } else {
                $this->pdo->exec("SAVEPOINT sp_{$this->depth}");
            }
        } catch (PDOException $e) {
            throw new TransactionException('Failed to begin transaction: '.$e->getMessage(), previous: $e);
        }
        ++$this->depth;
    }

    public function commit(): void
    {
        if (0 === $this->depth) {
            throw new TransactionException('Cannot commit: no active transaction.');
        }
        --$this->depth;
        try {
            if (0 === $this->depth) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec("RELEASE SAVEPOINT sp_{$this->depth}");
            }
        } catch (PDOException $e) {
            throw new TransactionException('Failed to commit transaction: '.$e->getMessage(), previous: $e);
        }
    }

    public function rollback(): void
    {
        if (0 === $this->depth) {
            throw new TransactionException('Cannot rollback: no active transaction.');
        }
        --$this->depth;
        try {
            if (0 === $this->depth) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT sp_{$this->depth}");
            }
        } catch (PDOException $e) {
            throw new TransactionException('Failed to rollback transaction: '.$e->getMessage(), previous: $e);
        }
    }
}
