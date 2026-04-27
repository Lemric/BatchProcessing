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

namespace Lemric\BatchProcessing\Scope;

use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};

/**
 * Lazy-initialization proxy for job-scoped components.
 * The factory callable receives the current {@see JobExecution} and {@see JobParameters}
 * and returns the actual component instance. Created once per job execution.
 *
 * @template T
 *
 * @extends AbstractScope<T>
 */
final class JobScope extends AbstractScope
{
    /**
     * @param callable(JobExecution, JobParameters): T $factory
     */
    public function __construct(
        private $factory,
    ) {
    }

    /**
     * @return T
     */
    public function get(JobExecution $jobExecution): mixed
    {
        $this->ensureActive('JobScope');
        if (null === $this->instance) {
            $this->instance = ($this->factory)(
                $jobExecution,
                $jobExecution->getJobParameters(),
            );
        }

        return $this->instance;
    }
}
