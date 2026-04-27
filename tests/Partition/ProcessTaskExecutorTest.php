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
use Lemric\BatchProcessing\Partition\ProcessTaskExecutor;
use Lemric\BatchProcessing\Step\StepInterface;
use PHPUnit\Framework\TestCase;

final class ProcessTaskExecutorTest extends TestCase
{
    public function testEmptyPartitionsNoOp(): void
    {
        $executor = new ProcessTaskExecutor();
        $step = $this->createMock(StepInterface::class);
        $step->expects(self::never())->method('execute');

        $executor->handle($step, []);
        $this->addToAssertionCount(1);
    }

    public function testFallsBackToSequentialWithoutPcntl(): void
    {
        // ProcessTaskExecutor should always work sequentially as a fallback if pcntl is missing.
        // In the test environment pcntl may or may not be available — we test the sequential path.
        if (extension_loaded('pcntl')) {
            self::markTestSkipped('pcntl is loaded; cannot test sequential fallback.');
        }

        $executor = new ProcessTaskExecutor(maxConcurrent: 2);
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
        for ($i = 0; $i < 3; ++$i) {
            $partitions[] = new StepExecution("p{$i}", $jobExecution, $i + 1);
        }

        $executor->handle($step, $partitions);

        self::assertCount(3, $executed);
    }
}
