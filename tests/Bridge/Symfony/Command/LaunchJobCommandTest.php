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

use Lemric\BatchProcessing\Bridge\Symfony\Command\LaunchJobCommand;
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class LaunchJobCommandTest extends TestCase
{
    public function testLaunchCreatesExecution(): void
    {
        $operator = $this->createMock(JobOperatorInterface::class);
        $operator->expects(self::once())
            ->method('start')
            ->with('myJob', self::isInstanceOf(JobParameters::class))
            ->willReturn(42);

        $tester = new CommandTester(new LaunchJobCommand($operator));
        $tester->execute(['jobName' => 'myJob', '--param' => ['key:value']]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('42', $tester->getDisplay());
    }

    public function testLaunchFailure(): void
    {
        $operator = $this->createMock(JobOperatorInterface::class);
        $operator->method('start')->willThrowException(new RuntimeException('boom'));

        $tester = new CommandTester(new LaunchJobCommand($operator));
        $tester->execute(['jobName' => 'myJob']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('failed to launch', $tester->getDisplay());
    }

    public function testLaunchNextInstance(): void
    {
        $operator = $this->createMock(JobOperatorInterface::class);
        $operator->expects(self::once())
            ->method('startNextInstance')
            ->with('myJob')
            ->willReturn(7);

        $tester = new CommandTester(new LaunchJobCommand($operator));
        $tester->execute(['jobName' => 'myJob', '--next' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('7', $tester->getDisplay());
    }
}
