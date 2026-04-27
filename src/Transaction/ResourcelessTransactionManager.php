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
 * No-op transaction manager useful in tests, in-memory pipelines or when the writer manages
 * its own transactions.
 */
final class ResourcelessTransactionManager implements TransactionManagerInterface
{
    private int $depth = 0;

    public function begin(): void
    {
        ++$this->depth;
    }

    public function commit(): void
    {
        if ($this->depth > 0) {
            --$this->depth;
        }
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function rollback(): void
    {
        if ($this->depth > 0) {
            --$this->depth;
        }
    }
}
