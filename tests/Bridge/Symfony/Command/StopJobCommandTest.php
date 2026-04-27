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

use Lemric\BatchProcessing\Bridge\Symfony\Command\StopJobCommand;
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class StopJobCommandTest extends TestCase
{
    public function testStopNonRunning(): void
    {
        $operator = $this->createMock(JobOperatorInterface::class);
        $operator->method('stop')->willReturn(false);

        $tester = new CommandTester(new StopJobCommand($operator));
        $tester->execute(['executionId' => '99']);

        self::assertSame(1, $tester->getStatusCode());
    }

    public function testStopRunningExecution(): void
    {
        $operator = $this->createMock(JobOperatorInterface::class);
        $operator->expects(self::once())->method('stop')->with(42)->willReturn(true);

        $tester = new CommandTester(new StopJobCommand($operator));
        $tester->execute(['executionId' => '42']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Stop requested', $tester->getDisplay());
    }
}
