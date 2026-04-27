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

namespace Lemric\BatchProcessing\Tests\Bridge\Symfony\Command;

use Lemric\BatchProcessing\Bridge\Symfony\Command\ListJobExecutionsCommand;
use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, JobExecution, JobInstance, JobParameters};
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ListJobExecutionsCommandTest extends TestCase
{
    public function testListEmpty(): void
    {
        $explorer = $this->createMock(JobExplorerInterface::class);
        $explorer->method('getJobNames')->willReturn([]);

        $tester = new CommandTester(new ListJobExecutionsCommand($explorer));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No job executions', $tester->getDisplay());
    }

    public function testListExecutions(): void
    {
        $instance = new JobInstance(1, 'testJob', 'key');
        $execution = new JobExecution(1, $instance, new JobParameters());
        $execution->setStatus(BatchStatus::COMPLETED);
        $execution->setExitStatus(ExitStatus::$COMPLETED);

        $explorer = $this->createMock(JobExplorerInterface::class);
        $explorer->method('getJobNames')->willReturn(['testJob']);
        $explorer->method('getJobInstances')->willReturn([$instance]);
        $explorer->method('getJobExecutions')->willReturn([$execution]);

        $tester = new CommandTester(new ListJobExecutionsCommand($explorer));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('testJob', $tester->getDisplay());
    }
}
