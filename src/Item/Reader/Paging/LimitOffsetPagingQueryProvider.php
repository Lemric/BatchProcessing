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

namespace Lemric\BatchProcessing\Item\Reader\Paging;

use Lemric\BatchProcessing\Security\UnsafeSqlQueryFragmentValidator;

/**
 * Generic LIMIT/OFFSET paging (MySQL, PostgreSQL, SQLite).
 */
final class LimitOffsetPagingQueryProvider implements SqlPagingQueryProviderInterface
{
    /**
     * @param string $selectClause e.g. "SELECT id, name"
     * @param string $fromClause   e.g. "FROM users"
     * @param string $whereClause  e.g. "WHERE active = 1" (can be empty)
     * @param string $sortKeys     e.g. "ORDER BY id ASC"
     */
    public function __construct(
        private readonly string $selectClause,
        private readonly string $fromClause,
        private readonly string $whereClause = '',
        private readonly string $sortKeys = '',
    ) {
        UnsafeSqlQueryFragmentValidator::assertPagingQueryFragment(mb_trim($selectClause), 'SELECT clause');
        UnsafeSqlQueryFragmentValidator::assertPagingQueryFragment(mb_trim($fromClause), 'FROM clause');
        if ('' !== $whereClause) {
            UnsafeSqlQueryFragmentValidator::assertPagingQueryFragment(mb_trim($whereClause), 'WHERE clause');
        }
        if ('' !== $sortKeys) {
            UnsafeSqlQueryFragmentValidator::assertPagingQueryFragment(mb_trim($sortKeys), 'ORDER BY clause');
        }
    }

    public function generateCountQuery(): string
    {
        $where = '' !== $this->whereClause ? ' '.$this->whereClause : '';

        return "SELECT COUNT(*) {$this->fromClause}{$where}";
    }

    public function generateQuery(int $offset, int $pageSize): string
    {
        $where = '' !== $this->whereClause ? ' '.$this->whereClause : '';
        $sort = '' !== $this->sortKeys ? ' '.$this->sortKeys : '';

        return "{$this->selectClause} {$this->fromClause}{$where}{$sort} LIMIT {$pageSize} OFFSET {$offset}";
    }
}
