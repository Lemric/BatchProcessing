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

use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution, JobInstance, JobParameters, StepExecution};
use Lemric\BatchProcessing\Partition\FiberTaskExecutor;
use Lemric\BatchProcessing\Step\StepInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FiberTaskExecutorTest extends TestCase
{
    public function testEmptyPartitionsNoOp(): void
    {
        $executor = new FiberTaskExecutor();
        $step = $this->createMock(StepInterface::class);
        $step->expects(self::never())->method('execute');

        $executor->handle($step, []);
        // No assertions needed — just verifying it doesn't blow up.
        $this->addToAssertionCount(1);
    }

    public function testExecutesAllPartitions(): void
    {
        $executor = new FiberTaskExecutor(maxConcurrent: 4);
        $executed = [];

        $step = $this->createMock(StepInterface::class);
        $step->method('getName')->willReturn('worker');
        $step->method('execute')->willReturnCallback(function (StepExecution $se) use (&$executed): void {
            $executed[] = $se->getStepName();
            $se->setStatus(BatchStatus::COMPLETED);
        });

        $instance = new JobInstance(1, 'testJob', 'key');
        $jobExecution = new JobExecution(1, $instance, new JobParameters());
        $partitions = [];
        for ($i = 0; $i < 8; ++$i) {
            $partitions[] = new StepExecution("partition{$i}", $jobExecution, $i + 1);
        }

        $executor->handle($step, $partitions);

        self::assertCount(8, $executed);
    }

    public function testPropagatesFirstError(): void
    {
        $executor = new FiberTaskExecutor(maxConcurrent: 2);

        $step = $this->createMock(StepInterface::class);
        $step->method('getName')->willReturn('worker');
        $step->method('execute')->willThrowException(new RuntimeException('boom'));

        $instance = new JobInstance(1, 'testJob', 'key');
        $jobExecution = new JobExecution(1, $instance, new JobParameters());
        $partitions = [new StepExecution('p0', $jobExecution, 1)];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $executor->handle($step, $partitions);
    }
}
