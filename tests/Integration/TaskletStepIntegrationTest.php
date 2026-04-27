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

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters, StepContribution};
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Step\{RepeatStatus, StepBuilderFactory, TaskletInterface};
use Lemric\BatchProcessing\Testing\JobLauncherTestUtils;
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;

final class TaskletStepIntegrationTest extends TestCase
{
    public function testTaskletExecutesUntilFinished(): void
    {
        $repo = new InMemoryJobRepository();
        $tx = new ResourcelessTransactionManager();
        $stepFactory = new StepBuilderFactory($repo, $tx);
        $jobFactory = new JobBuilderFactory($repo);

        $invocations = 0;
        $tasklet = new class($invocations) implements TaskletInterface {
            public function __construct(private int &$invocations)
            {
            }

            public function execute(StepContribution $contribution, ChunkContext $chunkContext): RepeatStatus
            {
                ++$this->invocations;

                return $this->invocations < 3 ? RepeatStatus::CONTINUABLE : RepeatStatus::FINISHED;
            }
        };

        $step = $stepFactory->get('taskletStep')->tasklet($tasklet)->build();
        $job = $jobFactory->get('taskletJob')->start($step)->build();

        $execution = new JobLauncherTestUtils($repo)->launchJob($job, JobParameters::of(['run.id' => 1]));
        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
        self::assertSame(3, $invocations);
        self::assertSame(3, $execution->getStepExecutions()[0]->getCommitCount());
    }
}
