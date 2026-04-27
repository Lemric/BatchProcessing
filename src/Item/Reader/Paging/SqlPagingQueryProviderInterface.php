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

/**
 * Provides a paging SQL query for a specific database dialect.
 */
interface SqlPagingQueryProviderInterface
{
    /**
     * Generate a COUNT query matching the same WHERE clause.
     */
    public function generateCountQuery(): string;

    /**
     * Generate a paged SELECT statement.
     */
    public function generateQuery(int $offset, int $pageSize): string;
}
