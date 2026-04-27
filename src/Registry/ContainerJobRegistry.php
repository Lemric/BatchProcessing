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
use Lemric\BatchProcessing\Exception\{BatchException, NoSuchJobException};
use Lemric\BatchProcessing\Job\JobInterface;
use Psr\Container\ContainerInterface;

/**
 * Resolves jobs from a PSR-11 container by service id. The map is supplied as a {@code jobName => containerId}
 * dictionary; if no map is provided, the job name is used directly as the service id.
 */
final class ContainerJobRegistry implements JobRegistryInterface
{
    /** @var array<string, JobInterface> */
    private array $cache = [];

    /**
     * @param array<string, string> $aliases jobName => container service id
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private array $aliases = [],
    ) {
    }

    /**
     * Aliases a logical job name to a PSR-11 service id.
     */
    public function alias(string $jobName, string $serviceId): void
    {
        $this->aliases[$jobName] = $serviceId;
    }

    public function getJob(string $jobName): JobInterface
    {
        if (isset($this->cache[$jobName])) {
            return $this->cache[$jobName];
        }
        $serviceId = $this->aliases[$jobName] ?? $jobName;
        if (!$this->container->has($serviceId)) {
            throw new NoSuchJobException("No job named '{$jobName}' (service id '{$serviceId}') is registered in container.");
        }
        $instance = $this->container->get($serviceId);
        if (!$instance instanceof JobInterface) {
            throw new NoSuchJobException("Service '{$serviceId}' for job '{$jobName}' is not a JobInterface.");
        }
        $this->cache[$jobName] = $instance;

        return $instance;
    }

    public function getJobNames(): array
    {
        return array_values(array_unique(array_merge(array_keys($this->aliases), array_keys($this->cache))));
    }

    public function hasJob(string $jobName): bool
    {
        if (isset($this->cache[$jobName])) {
            return true;
        }
        $serviceId = $this->aliases[$jobName] ?? $jobName;

        return $this->container->has($serviceId);
    }

    /**
     * Registers a job. Behaviour:
     *  - {@see JobInterface}: cached locally (the container does not own the instance).
     *  - {@see Closure} / callable factory: invoked immediately and the returned job cached locally.
     */
    public function register(string $jobName, JobInterface|callable $jobOrFactory): void
    {
        if ($jobOrFactory instanceof JobInterface) {
            $this->cache[$jobName] = $jobOrFactory;

            return;
        }

        $instance = ($jobOrFactory instanceof Closure ? $jobOrFactory : Closure::fromCallable($jobOrFactory))();
        if (!$instance instanceof JobInterface) {
            throw new BatchException("Job factory for '{$jobName}' did not return a JobInterface.");
        }
        $this->cache[$jobName] = $instance;
    }
}
