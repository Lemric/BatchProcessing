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

use Lemric\BatchProcessing\Exception\NoSuchJobException;
use Lemric\BatchProcessing\Job\JobInterface;
use Psr\Container\ContainerInterface;

/**
 * Read-only PSR-11 powered {@see JobLocatorInterface}. Resolves jobs lazily; the resolved
 * instance is cached per locator. Names are pre-declared via the constructor (no runtime
 * reflection of the container).
 */
final class ContainerJobLocator implements JobLocatorInterface
{
    /** @var array<string, JobInterface> */
    private array $cache = [];

    /**
     * @param array<string, string> $serviceMap jobName => container service id
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private array $serviceMap,
    ) {
    }

    public function getJob(string $jobName): JobInterface
    {
        if (isset($this->cache[$jobName])) {
            return $this->cache[$jobName];
        }
        if (!isset($this->serviceMap[$jobName])) {
            throw new NoSuchJobException("No job named '{$jobName}' is registered in the locator.");
        }
        $serviceId = $this->serviceMap[$jobName];
        if (!$this->container->has($serviceId)) {
            throw new NoSuchJobException("Service '{$serviceId}' for job '{$jobName}' is missing from the container.");
        }
        $instance = $this->container->get($serviceId);
        if (!$instance instanceof JobInterface) {
            throw new NoSuchJobException("Service '{$serviceId}' for job '{$jobName}' is not a JobInterface.");
        }

        return $this->cache[$jobName] = $instance;
    }

    public function getJobNames(): array
    {
        return array_keys($this->serviceMap);
    }
}
