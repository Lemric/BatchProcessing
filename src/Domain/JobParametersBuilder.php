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

/**
 * Fluent builder for {@see JobParameters}.
 */
final class JobParametersBuilder
{
    /** @var array<string, JobParameter> */
    private array $parameters = [];

    public function __construct(?JobParameters $base = null)
    {
        if (null !== $base) {
            $this->parameters = $base->getParameters();
        }
    }

    public function addDate(string $name, ?DateTimeImmutable $value, bool $identifying = true): self
    {
        $this->parameters[$name] = JobParameter::ofDate($name, $value, $identifying);

        return $this;
    }

    public function addDouble(string $name, ?float $value, bool $identifying = true): self
    {
        $this->parameters[$name] = JobParameter::ofDouble($name, $value, $identifying);

        return $this;
    }

    public function addJobParameters(JobParameters $parameters): self
    {
        foreach ($parameters->getParameters() as $name => $parameter) {
            $this->parameters[$name] = $parameter;
        }

        return $this;
    }

    public function addLong(string $name, ?int $value, bool $identifying = true): self
    {
        $this->parameters[$name] = JobParameter::ofLong($name, $value, $identifying);

        return $this;
    }

    public function addParameter(JobParameter $parameter): self
    {
        $this->parameters[$parameter->getName()] = $parameter;

        return $this;
    }

    public function addString(string $name, ?string $value, bool $identifying = true): self
    {
        $this->parameters[$name] = JobParameter::ofString($name, $value, $identifying);

        return $this;
    }

    public function removeParameter(string $name): self
    {
        unset($this->parameters[$name]);

        return $this;
    }

    public function toJobParameters(): JobParameters
    {
        return new JobParameters($this->parameters);
    }
}
