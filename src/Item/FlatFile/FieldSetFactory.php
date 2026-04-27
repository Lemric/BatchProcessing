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

namespace Lemric\BatchProcessing\Item\FlatFile;

/**
 * Factory for creating {@see FieldSet} instances from raw values.
 */
final class FieldSetFactory
{
    /**
     * @param list<string> $names optional column names
     */
    public function __construct(
        private readonly array $names = [],
    ) {
    }

    /**
     * @param list<string> $values
     */
    public function create(array $values): FieldSet
    {
        return new DefaultFieldSet($values, $this->names);
    }
}
