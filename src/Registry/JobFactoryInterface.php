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

use Lemric\BatchProcessing\Job\JobInterface;

/**
 * Lazy factory used by {@see ContainerJobLocator} / {@see InMemoryJobRegistry}. Materialises a
 * {@see JobInterface} on first access.
 */
interface JobFactoryInterface
{
    public function createJob(): JobInterface;

    public function getJobName(): string;
}
