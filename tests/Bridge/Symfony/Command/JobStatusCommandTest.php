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

use Lemric\BatchProcessing\Bridge\Symfony\Command\JobStatusCommand;
use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, JobExecution, JobInstance, JobParameters};
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class JobStatusCommandTest extends TestCase
{
    public function testStatusDisplaysExecution(): void
    {
        $instance = new JobInstance(1, 'testJob', 'key');
        $execution = new JobExecution(1, $instance, new JobParameters());
        $execution->setStatus(BatchStatus::COMPLETED);
        $execution->setExitStatus(ExitStatus::$COMPLETED);

        $explorer = $this->createMock(JobExplorerInterface::class);
        $explorer->method('getJobExecution')->willReturn($execution);

        $tester = new CommandTester(new JobStatusCommand($explorer));
        $tester->execute(['executionId' => '1']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('COMPLETED', $tester->getDisplay());
    }

    public function testStatusNotFound(): void
    {
        $explorer = $this->createMock(JobExplorerInterface::class);
        $explorer->method('getJobExecution')->willReturn(null);

        $tester = new CommandTester(new JobStatusCommand($explorer));
        $tester->execute(['executionId' => '999']);

        self::assertSame(1, $tester->getStatusCode());
    }
}
