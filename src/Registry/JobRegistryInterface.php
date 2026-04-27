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

interface JobRegistryInterface
{
    public function getJob(string $jobName): JobInterface;

    /**
     * @return list<string>
     */
    public function getJobNames(): array;

    public function hasJob(string $jobName): bool;

    public function register(string $jobName, JobInterface|callable $jobOrFactory): void;
}
