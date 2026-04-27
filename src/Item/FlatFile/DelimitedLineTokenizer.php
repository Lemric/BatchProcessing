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
 * Tokenizes a delimited line (CSV) into a {@see FieldSet}.
 */
final class DelimitedLineTokenizer implements LineTokenizerInterface
{
    /**
     * @param list<string> $names optional column names
     */
    public function __construct(
        private readonly string $delimiter = ',',
        private readonly string $quoteCharacter = '"',
        private readonly array $names = [],
    ) {
    }

    public function tokenize(string $line): FieldSet
    {
        $fields = str_getcsv($line, $this->delimiter, $this->quoteCharacter, '');
        /** @var list<string> $values */
        $values = array_map(static fn ($v): string => (string) ($v ?? ''), $fields);

        return new DefaultFieldSet($values, $this->names);
    }
}
