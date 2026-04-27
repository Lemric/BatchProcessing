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

namespace Lemric\BatchProcessing\Registry;

use Closure;
use Lemric\BatchProcessing\Exception\BatchException;
use Lemric\BatchProcessing\Job\JobInterface;

/**
 * Convenience {@see JobFactoryInterface} that wraps a {@see Closure} or callable producing
 * the job. Useful for tests and ad-hoc job assembly outside a DI container.
 */
final readonly class ReferenceJobFactory implements JobFactoryInterface
{
    private Closure $factory;

    /**
     * @param callable(): JobInterface $factory
     */
    public function __construct(callable $factory, private string $jobName)
    {
        $this->factory = $factory instanceof Closure ? $factory : Closure::fromCallable($factory);
    }

    public function createJob(): JobInterface
    {
        $job = ($this->factory)();
        if (!$job instanceof JobInterface) {
            throw new BatchException("ReferenceJobFactory for '{$this->jobName}' did not return a JobInterface.");
        }

        return $job;
    }

    public function getJobName(): string
    {
        return $this->jobName;
    }
}
