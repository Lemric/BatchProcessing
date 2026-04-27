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

namespace Lemric\BatchProcessing\Tests\Job;

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution, JobInstance, JobParameters, StepContribution};
use Lemric\BatchProcessing\Job\SplitFlow;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Step\{RepeatStatus, TaskletInterface, TaskletStep};
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;

final class SplitFlowTest extends TestCase
{
    public function testEmptySplitFlowDoesNothing(): void
    {
        $split = new SplitFlow();
        $instance = new JobInstance(null, 'testJob', 'testJob');
        $jobExecution = new JobExecution(null, $instance, new JobParameters());
        $split->execute($jobExecution);
        self::assertCount(0, $split->getSteps());
        self::assertSame([], $jobExecution->getStepExecutions());
    }

    public function testExecutesStepsConcurrently(): void
    {
        $repository = new InMemoryJobRepository();
        $trace = new SplitFlowTestTrace();

        $step1 = new TaskletStep('split1', $repository, new class($trace) implements TaskletInterface {
            public function __construct(private SplitFlowTestTrace $trace)
            {
            }

            public function execute(StepContribution $c, ChunkContext $ctx): RepeatStatus
            {
                $this->trace->entries[] = 'split1';

                return RepeatStatus::FINISHED;
            }
        }, new ResourcelessTransactionManager());

        $step2 = new TaskletStep('split2', $repository, new class($trace) implements TaskletInterface {
            public function __construct(private SplitFlowTestTrace $trace)
            {
            }

            public function execute(StepContribution $c, ChunkContext $ctx): RepeatStatus
            {
                $this->trace->entries[] = 'split2';

                return RepeatStatus::FINISHED;
            }
        }, new ResourcelessTransactionManager());

        $split = new SplitFlow($step1, $step2);

        $instance = new JobInstance(null, 'testJob', 'testJob');
        $jobExecution = new JobExecution(null, $instance, new JobParameters());
        $jobExecution->setStatus(BatchStatus::STARTED);

        $split->execute($jobExecution);

        self::assertContains('split1', $trace->entries);
        self::assertContains('split2', $trace->entries);
        self::assertCount(2, $trace->entries);
    }

    public function testGetSteps(): void
    {
        $repository = new InMemoryJobRepository();
        $step = new TaskletStep('s', $repository, new class implements TaskletInterface {
            public function execute(StepContribution $c, ChunkContext $ctx): RepeatStatus
            {
                return RepeatStatus::FINISHED;
            }
        }, new ResourcelessTransactionManager());

        $split = new SplitFlow($step);
        self::assertCount(1, $split->getSteps());
    }
}

final class SplitFlowTestTrace
{
    /** @var list<string> */
    public array $entries = [];
}
