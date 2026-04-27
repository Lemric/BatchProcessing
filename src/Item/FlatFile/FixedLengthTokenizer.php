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

use InvalidArgumentException;

/**
 * Tokenizes a fixed-width line into a {@see FieldSet} based on column ranges.
 */
final class FixedLengthTokenizer implements LineTokenizerInterface
{
    /**
     * @param list<array{int, int}> $columns array of [start, end] pairs (0-based, exclusive end)
     * @param list<string>          $names   optional column names
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $names = [],
    ) {
        if ([] === $columns) {
            throw new InvalidArgumentException('At least one column range must be specified.');
        }
    }

    public function tokenize(string $line): FieldSet
    {
        $values = [];
        foreach ($this->columns as [$start, $end]) {
            $values[] = mb_trim(mb_substr($line, $start, $end - $start));
        }

        return new DefaultFieldSet($values, $this->names);
    }
}
