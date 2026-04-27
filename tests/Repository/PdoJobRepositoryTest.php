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

namespace Lemric\BatchProcessing\Tests\Repository;

use DateTimeImmutable;
use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, JobParameters};
use Lemric\BatchProcessing\Repository\{PdoJobRepository, PdoJobRepositorySchema};
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoJobRepositoryTest extends TestCase
{
    private PDO $pdo;

    private PdoJobRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (PdoJobRepositorySchema::sqlForPdo($this->pdo) as $stmt) {
            $this->pdo->exec($stmt);
        }
        $this->repo = new PdoJobRepository($this->pdo);
    }

    public function testFindRunningJobExecutions(): void
    {
        $params = JobParameters::of(['k' => 'v']);
        $instance = $this->repo->createJobInstance('runningJob', $params);
        $execution = $this->repo->createJobExecution($instance, $params);
        $execution->setStatus(BatchStatus::STARTED);
        $this->repo->updateJobExecution($execution);

        $running = $this->repo->findRunningJobExecutions('runningJob');
        self::assertCount(1, $running);
        self::assertSame($execution->getId(), $running[0]->getId());
    }

    public function testRoundTripJobAndStepExecution(): void
    {
        $params = JobParameters::of(['run.id' => 7, 'date' => '2025-04-25']);
        $instance = $this->repo->createJobInstance('myJob', $params);
        self::assertNotNull($instance->getId());

        $execution = $this->repo->createJobExecution($instance, $params);
        self::assertNotNull($execution->getId());

        $execution->setStatus(BatchStatus::STARTED);
        $execution->setStartTime(new DateTimeImmutable());
        $this->repo->updateJobExecution($execution);

        $step = $execution->createStepExecution('s1');
        $this->repo->add($step);
        $step->setStatus(BatchStatus::STARTED);
        $step->setStartTime(new DateTimeImmutable());
        $step->incrementReadCount();
        $step->incrementWriteCount(2);
        $step->incrementCommitCount();
        $step->getExecutionContext()->put('checkpoint', 42);
        $this->repo->update($step);
        $this->repo->updateExecutionContext($step);

        $execution->setStatus(BatchStatus::COMPLETED);
        $execution->setEndTime(new DateTimeImmutable());
        $execution->setExitStatus(ExitStatus::$COMPLETED);
        $this->repo->updateJobExecution($execution);

        $executionId = $execution->getId();
        self::assertNotNull($executionId);
        $reloaded = $this->repo->getJobExecution($executionId);
        self::assertNotNull($reloaded);
        self::assertSame(BatchStatus::COMPLETED, $reloaded->getStatus());
        self::assertSame(7, $reloaded->getJobParameters()->getLong('run.id'));

        $lastStep = $this->repo->getLastStepExecution($instance, 's1');
        self::assertNotNull($lastStep);
        self::assertSame(2, $lastStep->getWriteCount());
        self::assertSame(42, $lastStep->getExecutionContext()->getInt('checkpoint'));

        self::assertCount(1, $this->repo->findJobInstancesByName('myJob'));
        self::assertSame(1, $this->repo->getStepExecutionCount($instance, 's1'));
    }
}
