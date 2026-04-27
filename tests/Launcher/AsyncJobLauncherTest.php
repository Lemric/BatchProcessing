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

namespace Lemric\BatchProcessing\Tests\Launcher;

use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters};
use Lemric\BatchProcessing\Job\SimpleJob;
use Lemric\BatchProcessing\Launcher\AsyncJobLauncher;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use PHPUnit\Framework\TestCase;

final class AsyncJobLauncherTest extends TestCase
{
    public function testAsyncLauncherDispatchesWithoutExecuting(): void
    {
        $repository = new InMemoryJobRepository();
        $dispatched = [];

        $launcher = new AsyncJobLauncher(
            $repository,
            static function (int $executionId, string $jobName, JobParameters $params) use (&$dispatched): void {
                $dispatched[] = ['id' => $executionId, 'job' => $jobName];
            },
        );

        $job = new SimpleJob('asyncJob', $repository);
        $params = new JobParameters([]);
        $execution = $launcher->run($job, $params);

        // Job should be in STARTING status (not executed).
        self::assertSame(BatchStatus::STARTING, $execution->getStatus());
        self::assertCount(1, $dispatched);
        self::assertSame('asyncJob', $dispatched[0]['job']);
        self::assertSame($execution->getId(), $dispatched[0]['id']);
    }
}
