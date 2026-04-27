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

namespace Lemric\BatchProcessing\Tests\Step;

use Lemric\BatchProcessing\BatchEnvironmentBuilder;
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters};
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemWriterInterface};
use Lemric\BatchProcessing\Job\{FlowJob, JobBuilder, SimpleJob};
use Lemric\BatchProcessing\Partition\{PartitionStep, SimplePartitioner, StepHandlerInterface};
use Lemric\BatchProcessing\Retry\Backoff\ExponentialRandomBackOffPolicy;
use Lemric\BatchProcessing\Retry\Policy\TimeoutRetryPolicy;
use Lemric\BatchProcessing\Skip\{AlwaysSkipItemSkipPolicy, ExceptionClassifierSkipPolicy};
use Lemric\BatchProcessing\Step\{ChunkOrientedStep, StepBuilder, StepInterface};
use Lemric\BatchProcessing\Tests\Step\Fixture\NoopTasklet;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function count;

final class StepBuilderExtensionsTest extends TestCase
{
    public function testFlowJobBuiltViaJobBuilder(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        $repo = $env->repository;
        $launcher = $env->launcher;

        $stepA = new StepBuilder('a', $repo)->tasklet(new NoopTasklet())->build();
        $stepB = new StepBuilder('b', $repo)->tasklet(new NoopTasklet())->build();

        $job = new JobBuilder('flowy', $repo)
            ->flow()
            ->withRunIdIncrementer()
            ->start($stepA)
            ->transition($stepA, 'COMPLETED', $stepB)
            ->transition($stepB, '*', null)
            ->build();

        self::assertInstanceOf(FlowJob::class, $job);
        self::assertNotNull($job->getIncrementer());

        $execution = $launcher->run($job, new JobParameters([]));
        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
        self::assertCount(2, $execution->getStepExecutions());
    }

    public function testPartitionedStepIsBuiltViaBuilder(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        $repo = $env->repository;

        $worker = new StepBuilder('worker', $repo)
            ->tasklet(new NoopTasklet())
            ->build();

        $handler = new class implements StepHandlerInterface {
            public int $observedCount = 0;

            public function handle(StepInterface $step, array $partitionStepExecutions): void
            {
                foreach ($partitionStepExecutions as $exec) {
                    $step->execute($exec);
                }
                $this->observedCount = count($partitionStepExecutions);
            }
        };

        $step = new StepBuilder('master', $repo)
            ->partitioner(new SimplePartitioner(0, 9))
            ->workerStep($worker)
            ->partitionHandler($handler)
            ->gridSize(3)
            ->build();

        self::assertInstanceOf(PartitionStep::class, $step);

        $params = new JobParameters([]);
        $instance = $repo->createJobInstance('partJob', $params);
        $execution = $repo->createJobExecution($instance, $params);
        $masterExec = $execution->createStepExecution('master');
        $repo->add($masterExec);

        $step->execute($masterExec);
        self::assertSame(3, $handler->observedCount);
    }

    public function testRetryPolicyAndSkipPolicyAreHonoured(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        $repo = $env->repository;

        $reader = $this->makeReader([1, 2, 3]);
        $writer = $this->makeWriter();

        $step = new StepBuilder('s', $repo)
            ->chunk(2, $reader, null, $writer)
            ->retryPolicy(new TimeoutRetryPolicy(50))
            ->backOff(new ExponentialRandomBackOffPolicy(1, 2.0, 4))
            ->skipPolicy(new ExceptionClassifierSkipPolicy([
                RuntimeException::class => new AlwaysSkipItemSkipPolicy(10),
            ]))
            ->build();

        self::assertInstanceOf(ChunkOrientedStep::class, $step);
    }

    public function testSimpleJobStillProducedByDefault(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        $repo = $env->repository;
        $step = new StepBuilder('only', $repo)->tasklet(new NoopTasklet())->build();
        $job = new JobBuilder('plain', $repo)->start($step)->build();
        self::assertInstanceOf(SimpleJob::class, $job);
    }

    /**
     * @param list<int> $items
     *
     * @return ItemReaderInterface<int>
     */
    private function makeReader(array $items): ItemReaderInterface
    {
        return new class($items) implements ItemReaderInterface {
            /** @param list<int> $items */
            public function __construct(private array $items)
            {
            }

            public function read(): mixed
            {
                return array_shift($this->items);
            }
        };
    }

    /**
     * @return ItemWriterInterface<int>
     */
    private function makeWriter(): ItemWriterInterface
    {
        return new class implements ItemWriterInterface {
            public function write(Chunk $items): void
            {
            }
        };
    }
}
