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
use Stringable;

/**
 * Aggregates fields extracted from a domain object using a delimiter.
 */
final class DelimitedLineAggregator implements LineAggregatorInterface
{
    public function __construct(
        private readonly FieldExtractorInterface $fieldExtractor,
        private readonly string $delimiter = ',',
    ) {
    }

    public function aggregate(mixed $item): string
    {
        $fields = $this->fieldExtractor->extract($item);

        return implode($this->delimiter, array_map(
            function (mixed $f): string {
                $s = match (true) {
                    null === $f => '',
                    is_scalar($f), $f instanceof Stringable => (string) $f,
                    default => throw new InvalidArgumentException(sprintf('Cannot aggregate non-scalar field of type %s', get_debug_type($f))),
                };
                if ('' !== $s && 1 === preg_match('/^[=+\-@]/', $s)) {
                    return "'".str_replace("'", "''", $s);
                }

                return $s;
            },
            $fields,
        ));
    }
}
