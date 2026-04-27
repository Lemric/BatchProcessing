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

namespace Lemric\BatchProcessing\Item\Reader;

/**
 * Maps a parsed CSV field set (row) into a domain object.
 *
 * @template TItem
 */
interface CsvFieldSetMapperInterface
{
    /**
     * @param list<string> $fields the CSV columns for this row
     *
     * @return TItem
     */
    public function mapFieldSet(array $fields): mixed;
}
