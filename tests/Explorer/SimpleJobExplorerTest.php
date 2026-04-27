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

namespace Lemric\BatchProcessing\Tests\Explorer;

use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters, StepExecution};
use Lemric\BatchProcessing\Explorer\SimpleJobExplorer;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use PHPUnit\Framework\TestCase;

final class SimpleJobExplorerTest extends TestCase
{
    private SimpleJobExplorer $explorer;

    private InMemoryJobRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new InMemoryJobRepository();
        $this->explorer = new SimpleJobExplorer($this->repo);
    }

    public function testFindRunningJobExecutions(): void
    {
        $params = JobParameters::of(['run.id' => 1]);
        $instance = $this->repo->createJobInstance('testJob', $params);
        $execution = $this->repo->createJobExecution($instance, $params);
        $execution->setStatus(BatchStatus::STARTED);
        $this->repo->updateJobExecution($execution);

        $running = $this->explorer->findRunningJobExecutions('testJob');
        self::assertNotEmpty($running);
    }

    public function testGetJobExecutionReturnsExistingExecution(): void
    {
        $params = JobParameters::of(['run.id' => 1]);
        $instance = $this->repo->createJobInstance('testJob', $params);
        $execution = $this->repo->createJobExecution($instance, $params);

        $found = $this->explorer->getJobExecution((int) $execution->getId());
        self::assertNotNull($found);
        self::assertSame($execution->getId(), $found->getId());
    }

    public function testGetJobExecutionReturnsNullForUnknownId(): void
    {
        self::assertNull($this->explorer->getJobExecution(999));
    }

    public function testGetJobExecutionsReturnsExecutionsForInstance(): void
    {
        $params = JobParameters::of(['run.id' => 1]);
        $instance = $this->repo->createJobInstance('testJob', $params);
        $this->repo->createJobExecution($instance, $params);

        $executions = $this->explorer->getJobExecutions($instance);
        self::assertCount(1, $executions);
    }

    public function testGetJobInstanceReturnsNullForUnknownId(): void
    {
        self::assertNull($this->explorer->getJobInstance(999));
    }

    public function testGetJobInstancesReturnsList(): void
    {
        $params = JobParameters::of(['run.id' => 1]);
        $this->repo->createJobInstance('testJob', $params);

        $instances = $this->explorer->getJobInstances('testJob');
        self::assertCount(1, $instances);
    }

    public function testGetJobNamesReturnsEmptyByDefault(): void
    {
        self::assertSame([], $this->explorer->getJobNames());
    }

    public function testGetStepExecutionFindsMatchingStep(): void
    {
        $params = JobParameters::of(['run.id' => 1]);
        $instance = $this->repo->createJobInstance('testJob', $params);
        $execution = $this->repo->createJobExecution($instance, $params);
        $stepExec = new StepExecution('step1', $execution, 42);

        $found = $this->explorer->getStepExecution((int) $execution->getId(), 42);
        self::assertNotNull($found);
        self::assertSame(42, $found->getId());
    }

    public function testGetStepExecutionReturnsNullForUnknownJobExecution(): void
    {
        self::assertNull($this->explorer->getStepExecution(999, 1));
    }

    public function testGetStepExecutionReturnsNullWhenStepIdNotFound(): void
    {
        $params = JobParameters::of(['run.id' => 1]);
        $instance = $this->repo->createJobInstance('testJob', $params);
        $execution = $this->repo->createJobExecution($instance, $params);
        new StepExecution('step1', $execution, 10);

        self::assertNull($this->explorer->getStepExecution((int) $execution->getId(), 999));
    }
}
