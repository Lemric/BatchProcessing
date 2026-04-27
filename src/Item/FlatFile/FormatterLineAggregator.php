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
 * Aggregates fields using a sprintf format string.
 */
final class FormatterLineAggregator implements LineAggregatorInterface
{
    public function __construct(
        private readonly FieldExtractorInterface $fieldExtractor,
        private readonly string $format,
    ) {
    }

    public function aggregate(mixed $item): string
    {
        $fields = $this->fieldExtractor->extract($item);
        $normalized = array_map(
            static fn (mixed $f): string|int|float|bool|null => match (true) {
                null === $f, is_scalar($f) => $f,
                $f instanceof Stringable => (string) $f,
                default => throw new InvalidArgumentException(sprintf('Cannot format non-scalar field of type %s', get_debug_type($f))),
            },
            $fields,
        );

        return sprintf($this->format, ...$normalized);
    }
}
