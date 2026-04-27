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

namespace Lemric\BatchProcessing\Job;

use Lemric\BatchProcessing\Domain\{JobParameter, JobParameters};

/**
 * Increments a numeric "run.id" parameter on every launch. Useful when the caller wants every
 * invocation to produce a fresh {@see JobInstance} without manually picking a unique identifier.
 */
final class RunIdIncrementer implements JobParametersIncrementerInterface
{
    public function __construct(private readonly string $key = 'run.id')
    {
    }

    public function getNext(?JobParameters $previous): JobParameters
    {
        $current = $previous?->getLong($this->key) ?? 0;
        $next = $current + 1;

        $params = $previous?->getParameters() ?? [];
        $params[$this->key] = JobParameter::ofLong($this->key, $next, identifying: true);

        return new JobParameters($params);
    }
}
