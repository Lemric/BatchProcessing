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

use function is_string;

/**
 * Default converter between flat properties (name => "value(type)" or scalar) and {@see JobParameters}.
 *
 * Format: "name=value,type" or short form "name=value" (defaults to STRING).
 * Used by CLI launchers, Symfony Messenger payload deserialization, etc.
 *
 * Examples:
 *   ["run.id" => "1,LONG", "name" => "alice"]  → JobParameters
 *   ["date" => "2025-01-01,DATE,false"]         → non-identifying date param
 */
final class DefaultJobParametersConverter
{
    /**
     * @param array<string, scalar|null> $properties
     */
    public function getJobParameters(array $properties): JobParameters
    {
        $builder = new JobParametersBuilder();
        foreach ($properties as $key => $rawValue) {
            $builder->addParameter($this->parseParameter($key, $rawValue));
        }

        return $builder->toJobParameters();
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getProperties(JobParameters $jobParameters): array
    {
        $out = [];
        foreach ($jobParameters->getParameters() as $name => $parameter) {
            $value = $parameter->valueAsString();
            $type = $parameter->getType();
            $identifying = $parameter->isIdentifying() ? '' : ',false';
            $out[$name] = sprintf('%s,%s%s', $value, $type, $identifying);
        }

        return $out;
    }

    private function parseParameter(string $name, mixed $rawValue): JobParameter
    {
        if (!is_string($rawValue)) {
            return match (true) {
                is_int($rawValue) => JobParameter::ofLong($name, $rawValue),
                is_float($rawValue) => JobParameter::ofDouble($name, $rawValue),
                is_bool($rawValue) => JobParameter::ofString($name, $rawValue ? 'true' : 'false'),
                null === $rawValue => JobParameter::ofString($name, null),
                default => throw new InvalidArgumentException("Unsupported parameter type for {$name}: ".get_debug_type($rawValue)),
            };
        }

        $parts = explode(',', $rawValue);
        $value = $parts[0];
        $type = $parts[1] ?? JobParameter::TYPE_STRING;
        $identifying = !isset($parts[2]) || 'false' !== mb_strtolower(mb_trim($parts[2]));
        $type = mb_strtoupper(mb_trim($type));

        return match ($type) {
            JobParameter::TYPE_LONG => JobParameter::ofLong($name, (int) $value, $identifying),
            JobParameter::TYPE_DOUBLE => JobParameter::ofDouble($name, (float) $value, $identifying),
            JobParameter::TYPE_DATE => JobParameter::ofDate($name, '' === $value ? null : new DateTimeImmutable($value), $identifying),
            JobParameter::TYPE_STRING => JobParameter::ofString($name, $value, $identifying),
            default => throw new InvalidArgumentException("Unknown parameter type: {$type}"),
        };
    }
}
