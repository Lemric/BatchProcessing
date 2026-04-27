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

use Lemric\BatchProcessing\Bridge\Symfony\Command\RestartJobCommand;
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class RestartJobCommandTest extends TestCase
{
    public function testRestartFailure(): void
    {
        $operator = $this->createMock(JobOperatorInterface::class);
        $operator->method('restart')->willThrowException(new RuntimeException('not found'));

        $tester = new CommandTester(new RestartJobCommand($operator));
        $tester->execute(['executionId' => '99']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('failed', $tester->getDisplay());
    }

    public function testRestartSuccess(): void
    {
        $operator = $this->createMock(JobOperatorInterface::class);
        $operator->expects(self::once())->method('restart')->with(42)->willReturn(43);

        $tester = new CommandTester(new RestartJobCommand($operator));
        $tester->execute(['executionId' => '42']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('43', $tester->getDisplay());
    }
}
