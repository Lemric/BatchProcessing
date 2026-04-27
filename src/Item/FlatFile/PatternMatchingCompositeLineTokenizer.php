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

use Lemric\BatchProcessing\Exception\ParseException;

/**
 * Selects a {@see LineTokenizerInterface} based on pattern matching against the line.
 */
final class PatternMatchingCompositeLineTokenizer implements LineTokenizerInterface
{
    /**
     * @param array<string, LineTokenizerInterface> $tokenizers regex pattern → tokenizer
     */
    public function __construct(
        private readonly array $tokenizers,
    ) {
    }

    public function tokenize(string $line): FieldSet
    {
        foreach ($this->tokenizers as $pattern => $tokenizer) {
            if (1 === preg_match($pattern, $line)) {
                return $tokenizer->tokenize($line);
            }
        }

        throw new ParseException(sprintf('No tokenizer pattern matches line: "%s"', mb_substr($line, 0, 80)));
    }
}
