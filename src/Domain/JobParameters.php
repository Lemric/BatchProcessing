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

use Countable;
use DateTimeImmutable;
use InvalidArgumentException;

use function count;
use function get_debug_type;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

use const DATE_ATOM;

/**
 * Immutable collection of {@see JobParameter} keyed by parameter name.
 *
 * Identifying parameters (the default) participate in the calculation of the {@see JobInstance}
 * key, which uniquely identifies a logical job run.
 */
final readonly class JobParameters implements Countable
{
    /**
     * @param array<string, JobParameter> $parameters
     */
    public function __construct(
        private array $parameters = [],
    ) {
    }

    public function count(): int
    {
        return count($this->parameters);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function get(string $key): ?JobParameter
    {
        return $this->parameters[$key] ?? null;
    }

    public function getDate(string $key, ?DateTimeImmutable $default = null): ?DateTimeImmutable
    {
        $param = $this->parameters[$key] ?? null;
        if (null === $param) {
            return $default;
        }
        $value = $param->getValue();

        return $value instanceof DateTimeImmutable ? $value : $default;
    }

    public function getDouble(string $key, ?float $default = null): ?float
    {
        $param = $this->parameters[$key] ?? null;
        if (null === $param) {
            return $default;
        }
        $value = $param->getValue();

        return is_float($value) ? $value : $default;
    }

    /**
     * @return array<string, JobParameter>
     */
    public function getIdentifyingParameters(): array
    {
        return array_filter(
            $this->parameters,
            static fn (JobParameter $p): bool => $p->isIdentifying(),
        );
    }

    public function getLong(string $key, ?int $default = null): ?int
    {
        $param = $this->parameters[$key] ?? null;
        if (null === $param) {
            return $default;
        }
        $value = $param->getValue();

        return is_int($value) ? $value : $default;
    }

    /**
     * @return array<string, JobParameter>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $param = $this->parameters[$key] ?? null;
        if (null === $param) {
            return $default;
        }
        $value = $param->getValue();
        if (null === $value) {
            return $default;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value->format(DATE_ATOM);
        }

        return (string) $value;
    }

    /**
     * Returns a new {@see JobParameters} containing only identifying parameters. Useful for
     * restart validation and {@see JobInstance} key calculation comparisons.
     */
    public function identifyingOnly(): self
    {
        return new self($this->getIdentifyingParameters());
    }

    public function isEmpty(): bool
    {
        return [] === $this->parameters;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function of(array $params): self
    {
        $mapped = [];
        foreach ($params as $key => $value) {
            if (null === $value) {
                $mapped[$key] = JobParameter::ofString($key, null);

                continue;
            }
            $mapped[$key] = match (true) {
                $value instanceof JobParameter => $value,
                is_string($value) => JobParameter::ofString($key, $value),
                is_int($value) => JobParameter::ofLong($key, $value),
                is_float($value) => JobParameter::ofDouble($key, $value),
                is_bool($value) => JobParameter::ofString($key, $value ? 'true' : 'false'),
                $value instanceof DateTimeImmutable => JobParameter::ofDate($key, $value),
                default => throw new InvalidArgumentException("Unsupported parameter type for key {$key}: ".get_debug_type($value)),
            };
        }

        return new self($mapped);
    }

    /**
     * Stable, deterministic textual representation of identifying parameters.
     */
    public function toIdentifyingString(): string
    {
        $parts = [];
        foreach ($this->getIdentifyingParameters() as $key => $param) {
            $parts[] = $key.'='.$param->valueAsString();
        }
        sort($parts);

        return implode(',', $parts);
    }

    /**
     * Hash used as the {@code job_key} in the metadata schema.
     */
    public function toJobKey(): string
    {
        return hash('sha256', $this->toIdentifyingString());
    }
}
