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

use InvalidArgumentException;
use Stringable;

use function array_key_exists;
use function in_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_scalar;

/**
 * A persistable map of context state shared between chunk executions.
 *
 * The framework persists the context after every successful chunk commit; on restart the same
 * map is restored, allowing readers / writers to resume from the last checkpoint.
 *
 * Values must be JSON-serializable.
 */
final class ExecutionContext
{
    public const string FILTER_COUNT = 'batch.filter_count';

    public const string PROCESS_SKIP = 'batch.process_skip';

    public const string READ_COUNT = 'batch.read_count';

    public const string READ_SKIP = 'batch.read_skip';

    public const string WRITE_COUNT = 'batch.write_count';

    public const string WRITE_SKIP = 'batch.write_skip';

    private bool $dirty = false;

    /** @var array<string, bool> */
    private array $dirtyKeys = [];

    /**
     * @param array<string, mixed> $map
     */
    public function __construct(private array $map = [])
    {
    }

    public function clearDirtyFlag(): void
    {
        $this->dirty = false;
        $this->dirtyKeys = [];
    }

    public function containsKey(string $key): bool
    {
        return array_key_exists($key, $this->map);
    }

    public function containsValue(mixed $value): bool
    {
        return in_array($value, $this->map, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->map[$key] ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) ($this->map[$key] ?? $default);
    }

    public function getDouble(string $key, float $default = 0.0): float
    {
        return $this->getFloat($key, $default);
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->map[$key] ?? $default;
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->map[$key] ?? $default;
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->map[$key] ?? $default;
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Returns an immutable snapshot of this context. Further mutations on the
     * original will not affect the snapshot and vice-versa.
     */
    public static function immutable(ExecutionContext $source): self
    {
        return new self($source->toArray());
    }

    public function isDirty(?string $key = null): bool
    {
        if (null !== $key) {
            return $this->dirtyKeys[$key] ?? false;
        }

        return $this->dirty;
    }

    public function isEmpty(): bool
    {
        return [] === $this->map;
    }

    public function merge(ExecutionContext $other): void
    {
        foreach ($other->toArray() as $key => $value) {
            $this->putMixed($key, $value);
        }
    }

    /**
     * @param int|float|string|bool|array<array-key, mixed>|null $value
     */
    public function put(string $key, int|float|string|bool|array|null $value): void
    {
        if (array_key_exists($key, $this->map) && $this->map[$key] === $value) {
            return;
        }
        $this->map[$key] = $value;
        $this->dirty = true;
        $this->dirtyKeys[$key] = true;
    }

    /**
     * @param int|float|string|bool|array<array-key, mixed>|null $value
     */
    public function putIfAbsent(string $key, int|float|string|bool|array|null $value): mixed
    {
        if (array_key_exists($key, $this->map)) {
            return $this->map[$key];
        }
        $this->put($key, $value);

        return null;
    }

    /**
     * Persists a value after validating it is JSON-serializable (scalar, array, or null).
     */
    public function putMixed(string $key, mixed $value): void
    {
        $this->put($key, self::coerceJsonSerializableValue($value));
    }

    public function remove(string $key): void
    {
        if (array_key_exists($key, $this->map)) {
            unset($this->map[$key]);
            $this->dirty = true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->map;
    }

    /**
     * @return int|float|string|bool|array<array-key, mixed>|null
     */
    private static function coerceJsonSerializableValue(mixed $value): int|float|string|bool|array|null
    {
        if (null === $value || is_array($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value) || is_string($value)) {
            return $value;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException('ExecutionContext only accepts JSON-serializable scalars, arrays, null, or Stringable values.');
    }
}
