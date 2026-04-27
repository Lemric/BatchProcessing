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

/**
 * Abstraction over a transactional resource. Implementations are typically PDO or DBAL based,
 * but the interface is intentionally narrow to support resource-less ("noop") and in-memory
 * tests.
 *
 * Implementations MUST support the following invariants:
 *  - {@see begin()} starts a transaction (or a logical save-point if already inside one).
 *  - {@see commit()} closes the most recently opened transaction successfully.
 *  - {@see rollback()} discards changes since the most recent {@see begin()}.
 */
interface TransactionManagerInterface
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;
}
