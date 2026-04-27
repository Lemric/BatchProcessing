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

/**
 * Value object providing typed access to a row of fields.
 */
interface FieldSet
{
    public function getFieldCount(): int;

    /**
     * @return list<string>
     */
    public function getNames(): array;

    /**
     * @return list<string>
     */
    public function getValues(): array;

    public function readBoolean(int|string $index): bool;

    public function readDate(int|string $index, string $pattern = 'Y-m-d'): DateTimeImmutable;

    public function readFloat(int|string $index): float;

    public function readInt(int|string $index): int;

    public function readString(int|string $index): string;
}
