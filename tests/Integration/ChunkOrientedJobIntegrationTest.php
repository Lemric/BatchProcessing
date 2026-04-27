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
use Lemric\BatchProcessing\Item\Processor\FilteringItemProcessor;
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Step\StepBuilderFactory;
use Lemric\BatchProcessing\Testing\{InMemoryItemWriter, JobLauncherTestUtils, MockItemReader};
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ChunkOrientedJobIntegrationTest extends TestCase
{
    public function testEndToEndChunkOrientedJob(): void
    {
        $repo = new InMemoryJobRepository();
        $tx = new ResourcelessTransactionManager();
        $stepFactory = new StepBuilderFactory($repo, $tx);
        $jobFactory = new JobBuilderFactory($repo);

        $reader = MockItemReader::ofList([1, 2, 3, 4, 5, 6, 7]);
        $processor = new FilteringItemProcessor(static fn (int $i): bool => 1 === $i % 2); // keep odd
        $writer = new InMemoryItemWriter();

        $step = $stepFactory->get('demoStep')
            ->chunk(2, $reader, $processor, $writer)
            ->build();

        $job = $jobFactory->get('demoJob')->start($step)->build();

        $utils = new JobLauncherTestUtils($repo);
        $execution = $utils->launchJob($job, JobParameters::of(['run.id' => 1]));

        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
        self::assertCount(1, $execution->getStepExecutions());

        $stepExec = $execution->getStepExecutions()[0];
        self::assertSame(7, $stepExec->getReadCount());
        self::assertSame(4, $stepExec->getWriteCount());      // odd numbers: 1,3,5,7
        self::assertSame(3, $stepExec->getFilterCount());     // 2,4,6 filtered
        self::assertSame([1, 3, 5, 7], $writer->getWrittenItems());
        self::assertGreaterThan(0, $stepExec->getCommitCount());
    }

    public function testFailureMarksJobFailed(): void
    {
        $repo = new InMemoryJobRepository();
        $tx = new ResourcelessTransactionManager();
        $stepFactory = new StepBuilderFactory($repo, $tx);
        $jobFactory = new JobBuilderFactory($repo);

        $reader = MockItemReader::ofList([1, 2, 3]);
        $writer = new InMemoryItemWriter(failOnInvocation: 1);

        $step = $stepFactory->get('failingStep')
            ->chunk(5, $reader, null, $writer)
            ->build();

        $job = $jobFactory->get('failingJob')->start($step)->build();

        $utils = new JobLauncherTestUtils($repo);
        $execution = $utils->launchJob($job, JobParameters::of(['run.id' => 1]));

        self::assertSame(BatchStatus::FAILED, $execution->getStatus());
        $stepExec = $execution->getStepExecutions()[0];
        self::assertGreaterThan(0, $stepExec->getRollbackCount());
    }

    public function testSkipPolicyAllowsJobToCompleteWithBadItems(): void
    {
        $repo = new InMemoryJobRepository();
        $tx = new ResourcelessTransactionManager();
        $stepFactory = new StepBuilderFactory($repo, $tx);
        $jobFactory = new JobBuilderFactory($repo);

        $reader = MockItemReader::ofList([1, 2, 3, 4, 5]);
        $writer = new InMemoryItemWriter(failOnInvocation: 1); // chunk fails first time

        $step = $stepFactory->get('skipStep')
            ->chunk(5, $reader, null, $writer)
            ->faultTolerant()
            ->skip(RuntimeException::class)
            ->skipLimit(10)
            ->build();

        $job = $jobFactory->get('skipJob')->start($step)->build();

        $utils = new JobLauncherTestUtils($repo);
        $execution = $utils->launchJob($job, JobParameters::of(['run.id' => 1]));

        // Original chunk failure → rollback → scan mode replays each item; the writer's
        // single-failure flag was already triggered, so subsequent writes succeed and the
        // entire chunk is committed item-by-item.
        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
        $stepExec = $execution->getStepExecutions()[0];
        self::assertSame(5, $stepExec->getReadCount());
        // 5 items written, even though there was a rollback during the initial chunk write.
        self::assertSame(5, $stepExec->getWriteCount());
    }
}
