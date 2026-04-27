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

namespace Lemric\BatchProcessing\Partition;

use Lemric\BatchProcessing\Step\StepInterface;
use Psr\Container\ContainerInterface;

/**
 * Locates steps by name from a PSR-11 container.
 */
final class ContainerStepLocator implements StepLocatorInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function getStep(string $name): StepInterface
    {
        if (!$this->container->has($name)) {
            throw new \Lemric\BatchProcessing\Exception\NoSuchJobException("Step '{$name}' not found in container.");
        }

        $step = $this->container->get($name);
        if (!$step instanceof StepInterface) {
            throw new \Lemric\BatchProcessing\Exception\NoSuchJobException("Step '{$name}' is not a ".StepInterface::class.' instance.');
        }

        return $step;
    }
}
