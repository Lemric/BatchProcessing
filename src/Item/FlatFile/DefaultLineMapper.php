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
 * Default {@see LineMapperInterface} composing a {@see LineTokenizerInterface} and a {@see FieldSetMapperInterface}.
 *
 * @template TItem
 *
 * @implements LineMapperInterface<TItem>
 */
final class DefaultLineMapper implements LineMapperInterface
{
    /**
     * @param FieldSetMapperInterface<TItem> $fieldSetMapper
     */
    public function __construct(
        private readonly LineTokenizerInterface $lineTokenizer,
        private readonly FieldSetMapperInterface $fieldSetMapper,
    ) {
    }

    public function mapLine(string $line, int $lineNumber): mixed
    {
        return $this->fieldSetMapper->mapFieldSet($this->lineTokenizer->tokenize($line));
    }
}
