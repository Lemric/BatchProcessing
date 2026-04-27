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

/**
 * Pure read-only locator for jobs registered in a backing store
 * (container, factory map). Strict subset of {@see JobRegistryInterface}; useful when a
 * consumer should not be able to register new jobs.
 */
interface JobLocatorInterface
{
    /**
     * @throws NoSuchJobException when no job is registered under {@code $jobName}
     */
    public function getJob(string $jobName): JobInterface;

    /**
     * @return list<string>
     */
    public function getJobNames(): array;
}
