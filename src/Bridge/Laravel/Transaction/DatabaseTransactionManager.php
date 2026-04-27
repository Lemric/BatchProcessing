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

namespace Lemric\BatchProcessing\Bridge\Laravel\Transaction;

use Illuminate\Database\ConnectionInterface;
use Lemric\BatchProcessing\Exception\TransactionException;
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;
use Throwable;

/**
 * {@see TransactionManagerInterface} backed by Laravel's database connection abstraction
 * ({@see ConnectionInterface::beginTransaction()}). Honours nested transactions through
 * Laravel's transaction-level counter (savepoints when supported by the driver).
 */
final class DatabaseTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function begin(): void
    {
        try {
            $this->connection->beginTransaction();
        } catch (Throwable $e) {
            throw new TransactionException('Failed to begin DB transaction: '.$e->getMessage(), previous: $e);
        }
    }

    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (Throwable $e) {
            throw new TransactionException('Failed to commit DB transaction: '.$e->getMessage(), previous: $e);
        }
    }

    public function rollback(): void
    {
        try {
            $this->connection->rollBack();
        } catch (Throwable $e) {
            throw new TransactionException('Failed to rollback DB transaction: '.$e->getMessage(), previous: $e);
        }
    }
}
