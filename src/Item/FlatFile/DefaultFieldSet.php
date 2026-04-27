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

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Default {@see FieldSet} implementation supporting both positional and named field access.
 */
final class DefaultFieldSet implements FieldSet
{
    /**
     * @param list<string> $values
     * @param list<string> $names  optional column names for named access
     */
    public function __construct(
        private readonly array $values,
        private readonly array $names = [],
    ) {
    }

    public function getFieldCount(): int
    {
        return count($this->values);
    }

    public function getNames(): array
    {
        return $this->names;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function readBoolean(int|string $index): bool
    {
        $value = mb_strtolower($this->values[$this->resolveIndex($index)]);

        return in_array($value, ['true', '1', 'yes', 'on'], true);
    }

    public function readDate(int|string $index, string $pattern = 'Y-m-d'): DateTimeImmutable
    {
        $value = $this->values[$this->resolveIndex($index)];
        $date = DateTimeImmutable::createFromFormat($pattern, $value);
        if (false === $date) {
            throw new InvalidArgumentException(sprintf('Field "%s" cannot be parsed as date with pattern "%s": "%s"', $index, $pattern, $value));
        }

        return $date;
    }

    public function readFloat(int|string $index): float
    {
        $value = $this->values[$this->resolveIndex($index)];
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" is not a valid float: "%s"', $index, $value));
        }

        return (float) $value;
    }

    public function readInt(int|string $index): int
    {
        $value = $this->values[$this->resolveIndex($index)];
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" is not a valid integer: "%s"', $index, $value));
        }

        return (int) $value;
    }

    public function readString(int|string $index): string
    {
        return $this->values[$this->resolveIndex($index)];
    }

    private function resolveIndex(int|string $index): int
    {
        if (is_int($index)) {
            if ($index < 0 || $index >= count($this->values)) {
                throw new InvalidArgumentException(sprintf('Index %d out of range [0, %d)', $index, count($this->values)));
            }

            return $index;
        }

        $pos = array_search($index, $this->names, true);
        if (false === $pos) {
            throw new InvalidArgumentException(sprintf('Unknown field name: "%s". Available: %s', $index, implode(', ', $this->names)));
        }

        return $pos;
    }
}
