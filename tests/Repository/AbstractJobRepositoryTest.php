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

namespace Lemric\BatchProcessing\Tests\Repository;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters, StepExecution};
use Lemric\BatchProcessing\Repository\AbstractJobRepository;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AbstractJobRepositoryTest extends TestCase
{
    public function testBuildJobKeyUsesParametersToJobKey(): void
    {
        $repo = new class extends AbstractJobRepository {
            public function add(StepExecution $stepExecution): void
            {
            }

            public function createJobExecution(JobInstance $instance, JobParameters $parameters): JobExecution
            {
                throw new LogicException();
            }

            public function createJobInstance(string $jobName, JobParameters $parameters): JobInstance
            {
                throw new LogicException();
            }

            public function findJobExecutions(JobInstance $instance): array
            {
                return [];
            }

            public function findJobInstancesByName(string $jobName, int $start = 0, int $count = 20): array
            {
                return [];
            }

            public function findRunningJobExecutions(string $jobName): array
            {
                return [];
            }

            public function getJobExecution(int $executionId): ?JobExecution
            {
                return null;
            }

            public function getJobInstance(int $instanceId): ?JobInstance
            {
                return null;
            }

            public function getJobInstanceByJobNameAndParameters(string $jobName, JobParameters $parameters): ?JobInstance
            {
                return null;
            }

            public function getLastJobExecution(JobInstance $instance): ?JobExecution
            {
                return null;
            }

            public function getLastJobInstance(string $jobName): ?JobInstance
            {
                return null;
            }

            public function getLastStepExecution(JobInstance $instance, string $stepName): ?StepExecution
            {
                return null;
            }

            public function getStepExecutionCount(JobInstance $instance, string $stepName): int
            {
                return 0;
            }

            public function isJobInstanceExists(string $jobName, JobParameters $parameters): bool
            {
                return false;
            }

            public function update(StepExecution $stepExecution): void
            {
            }

            public function updateExecutionContext(StepExecution $stepExecution): void
            {
            }

            public function updateJobExecution(JobExecution $jobExecution): void
            {
            }

            public function updateJobExecutionContext(JobExecution $jobExecution): void
            {
            }

            public function deleteJobExecution(int $executionId): void
            {
            }

            public function deleteJobInstance(int $instanceId): void
            {
            }

            public function getJobNames(): array
            {
                return [];
            }

            public function exposeBuildJobKey(JobParameters $p): string
            {
                return $this->buildJobKey($p);
            }
        };

        $params = JobParameters::of(['name' => 'test']);
        $key = $repo->exposeBuildJobKey($params);

        self::assertNotEmpty($key);
    }
}
