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

use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

final readonly class JobBuilderFactory
{
    public function __construct(private JobRepositoryInterface $jobRepository)
    {
    }

    public function get(string $name): JobBuilder
    {
        return new JobBuilder($name, $this->jobRepository);
    }
}
