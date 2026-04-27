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

namespace Lemric\BatchProcessing\Testing;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters};
use Lemric\BatchProcessing\Scope\JobScope;

/**
 * PHPUnit-friendly helper for {@see JobScope}-scoped components. See
 * {@see StepScopeTestExecutionListener} for the symmetrical {@code Step} version.
 */
final class JobScopeTestExecutionListener
{
    /** @var list<JobScope<mixed>> */
    private array $activeScopes = [];

    public function begin(string $jobName = 'test-job', ?JobParameters $parameters = null): JobExecution
    {
        return new JobExecution(
            null,
            new JobInstance(null, $jobName, 'test-key'),
            $parameters ?? new JobParameters(),
        );
    }

    public function end(): void
    {
        foreach ($this->activeScopes as $scope) {
            $scope->reset();
        }
        $this->activeScopes = [];
    }

    /**
     * @template T
     *
     * @param JobScope<T> $scope
     */
    public function track(JobScope $scope): void
    {
        $scope->activate();
        $this->activeScopes[] = $scope;
    }
}
