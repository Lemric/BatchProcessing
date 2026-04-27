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

namespace Lemric\BatchProcessing\Tests\Integration;

use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters};
use Lemric\BatchProcessing\Exception\{JobInstanceAlreadyCompleteException, JobRestartException};
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Launcher\SimpleJobLauncher;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Step\StepBuilderFactory;
use Lemric\BatchProcessing\Testing\{InMemoryItemWriter, MockItemReader};
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;

final class LauncherSemanticsTest extends TestCase
{
    public function testNonRestartableJobCannotRetryAfterFailure(): void
    {
        $repo = new InMemoryJobRepository();
        $tx = new ResourcelessTransactionManager();
        $stepFactory = new StepBuilderFactory($repo, $tx);
        $jobFactory = new JobBuilderFactory($repo);

        $reader = MockItemReader::ofList([1, 2, 3]);
        $writer = new InMemoryItemWriter(failOnInvocation: 1);

        $step = $stepFactory->get('s')->chunk(5, $reader, null, $writer)->build();
        $job = $jobFactory->get('nonRestartable')->start($step)->preventRestart()->build();
        $launcher = new SimpleJobLauncher($repo);
        $params = JobParameters::of(['run.id' => 1]);

        $first = $launcher->run($job, $params);
        self::assertSame(BatchStatus::FAILED, $first->getStatus());

        $this->expectException(JobRestartException::class);
        $launcher->run($job, $params);
    }

    public function testRelaunchingCompletedInstanceFails(): void
    {
        [$launcher, $job, $params] = $this->buildAlwaysSucceedingPipeline();

        $first = $launcher->run($job, $params);
        self::assertSame(BatchStatus::COMPLETED, $first->getStatus());

        $this->expectException(JobInstanceAlreadyCompleteException::class);
        $launcher->run($job, $params);
    }

    /**
     * @return array{0: SimpleJobLauncher, 1: \Lemric\BatchProcessing\Job\JobInterface, 2: JobParameters}
     */
    private function buildAlwaysSucceedingPipeline(): array
    {
        $repo = new InMemoryJobRepository();
        $tx = new ResourcelessTransactionManager();
        $stepFactory = new StepBuilderFactory($repo, $tx);
        $jobFactory = new JobBuilderFactory($repo);

        $reader = MockItemReader::ofList([1, 2]);
        $writer = new InMemoryItemWriter();

        $step = $stepFactory->get('s')->chunk(5, $reader, null, $writer)->build();
        $job = $jobFactory->get('happy')->start($step)->build();
        $launcher = new SimpleJobLauncher($repo);

        return [$launcher, $job, JobParameters::of(['run.id' => 1])];
    }
}
