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

namespace Lemric\BatchProcessing\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

use function in_array;

use const DATE_ATOM;

/**
 * Strongly-typed parameter value supplied to a Job at launch time.
 *
 * Identifying parameters participate in {@see JobInstance} identity computation.
 */
final readonly class JobParameter
{
    public const string TYPE_DATE = 'DATE';

    public const string TYPE_DOUBLE = 'DOUBLE';

    public const string TYPE_LONG = 'LONG';

    public const string TYPE_STRING = 'STRING';

    public function __construct(
        public string $name,
        public string|int|float|DateTimeImmutable|null $value,
        public string $type,
        public bool $identifying = true,
    ) {
        if (!in_array($type, [self::TYPE_STRING, self::TYPE_LONG, self::TYPE_DOUBLE, self::TYPE_DATE], true)) {
            throw new InvalidArgumentException("Unknown JobParameter type: {$type}");
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): string|int|float|DateTimeImmutable|null
    {
        return $this->value;
    }

    public function isIdentifying(): bool
    {
        return $this->identifying;
    }

    public static function ofDate(string $name, ?DateTimeImmutable $value, bool $identifying = true): self
    {
        return new self($name, $value, self::TYPE_DATE, $identifying);
    }

    public static function ofDouble(string $name, ?float $value, bool $identifying = true): self
    {
        return new self($name, $value, self::TYPE_DOUBLE, $identifying);
    }

    public static function ofLong(string $name, ?int $value, bool $identifying = true): self
    {
        return new self($name, $value, self::TYPE_LONG, $identifying);
    }

    public static function ofString(string $name, ?string $value, bool $identifying = true): self
    {
        return new self($name, $value, self::TYPE_STRING, $identifying);
    }

    public function valueAsString(): string
    {
        return match (true) {
            null === $this->value => '',
            $this->value instanceof DateTimeImmutable => $this->value->format(DATE_ATOM),
            default => (string) $this->value,
        };
    }
}
