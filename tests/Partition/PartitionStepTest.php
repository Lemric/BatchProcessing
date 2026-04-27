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

namespace Lemric\BatchProcessing\Tests\Partition;

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{BatchStatus, StepContribution};
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Partition\{PartitionStep, SimplePartitioner, TaskExecutorPartitionHandler};
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Step\RepeatStatus;
use Lemric\BatchProcessing\Step\{TaskletInterface, TaskletStep};
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function count;

final class PartitionStepTest extends TestCase
{
    public function testPartitionStepExecutesAllPartitions(): void
    {
        $repository = new InMemoryJobRepository();
        $executedPartitions = [];

        $workerStep = $this->createWorkerStep($repository, $executedPartitions);

        $partitioner = new SimplePartitioner(1, 100);
        $handler = new TaskExecutorPartitionHandler();

        $partitionStep = new PartitionStep(
            'partitionedStep',
            $repository,
            $partitioner,
            $workerStep,
            $handler,
        );
        $partitionStep->setGridSize(4);

        $params = new JobParameters([]);
        $instance = $repository->createJobInstance('partitionJob', $params);
        $jobExecution = $repository->createJobExecution($instance, $params);
        $masterStepExecution = $jobExecution->createStepExecution('partitionedStep');

        $partitionStep->execute($masterStepExecution);

        self::assertCount(4, $executedPartitions);
        self::assertSame(BatchStatus::COMPLETED, $masterStepExecution->getStatus());
    }

    public function testPartitionStepFailsWhenWorkerFails(): void
    {
        $repository = new InMemoryJobRepository();
        $callCount = 0;

        $tasklet = new class($callCount) implements TaskletInterface {
            public function __construct(private int &$count)
            {
            }

            public function execute(StepContribution $contribution, ChunkContext $chunkContext): RepeatStatus
            {
                ++$this->count;
                if (2 === $this->count) {
                    throw new RuntimeException('Worker failed');
                }

                return RepeatStatus::FINISHED;
            }
        };

        $workerStep = new TaskletStep('workerStep', $repository, $tasklet, new ResourcelessTransactionManager());

        $partitioner = new SimplePartitioner(1, 30);
        $handler = new TaskExecutorPartitionHandler();
        $partitionStep = new PartitionStep('partitionedStep', $repository, $partitioner, $workerStep, $handler);
        $partitionStep->setGridSize(3);

        $params = new JobParameters([]);
        $instance = $repository->createJobInstance('partitionJob', $params);
        $jobExecution = $repository->createJobExecution($instance, $params);
        $masterStepExecution = $jobExecution->createStepExecution('partitionedStep');

        $partitionStep->execute($masterStepExecution);

        self::assertSame(BatchStatus::FAILED, $masterStepExecution->getStatus());
    }

    public function testSimplePartitionerCreatesCorrectRanges(): void
    {
        $partitioner = new SimplePartitioner(min: 1, max: 100);
        $partitions = $partitioner->partition(4);

        self::assertCount(4, $partitions);
        self::assertSame(1, $partitions['partition0']->getInt('minValue'));
        self::assertSame(25, $partitions['partition0']->getInt('maxValue'));
        self::assertSame(76, $partitions['partition3']->getInt('minValue'));
        self::assertSame(100, $partitions['partition3']->getInt('maxValue'));
    }

    public function testSimplePartitionerWithUnevenRange(): void
    {
        $partitioner = new SimplePartitioner(min: 1, max: 10);
        $partitions = $partitioner->partition(3);

        self::assertGreaterThanOrEqual(3, count($partitions));
        // Last partition should cover through max.
        $lastKey = array_key_last($partitions);
        self::assertNotNull($lastKey);
        self::assertSame(10, $partitions[$lastKey]->getInt('maxValue'));
    }

    /**
     * @param list<string> $log
     */
    private function createWorkerStep(InMemoryJobRepository $repository, array &$log): TaskletStep
    {
        $tasklet = new class($log) implements TaskletInterface {
            /** @param list<string> $log */
            public function __construct(
                /** @phpstan-ignore property.onlyWritten */
                private array &$log,
            ) {
            }

            public function execute(StepContribution $contribution, ChunkContext $chunkContext): RepeatStatus
            {
                $ctx = $chunkContext->getStepExecution()->getExecutionContext();
                $this->log[] = $ctx->getInt('minValue').'-'.$ctx->getInt('maxValue');

                return RepeatStatus::FINISHED;
            }
        };

        return new TaskletStep('workerStep', $repository, $tasklet, new ResourcelessTransactionManager());
    }
}
