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

use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters};
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryJobRepositoryTest extends TestCase
{
    public function testCreateJobExecutionAndFind(): void
    {
        $repo = new InMemoryJobRepository();
        $instance = $repo->createJobInstance('jobA', JobParameters::of(['run.id' => 1]));
        $exec = $repo->createJobExecution($instance, JobParameters::of(['run.id' => 1]));

        self::assertNotNull($exec->getId());
        $byId = $repo->getJobExecution($exec->getId());
        self::assertSame($exec, $byId);

        $exec->setStatus(BatchStatus::STARTED);
        $repo->updateJobExecution($exec);
        self::assertCount(1, $repo->findRunningJobExecutions('jobA'));
    }

    public function testCreateJobInstanceIsIdempotentForSameKey(): void
    {
        $repo = new InMemoryJobRepository();
        $params = JobParameters::of(['run.id' => 1]);

        $a = $repo->createJobInstance('jobA', $params);
        $b = $repo->createJobInstance('jobA', $params);

        self::assertSame($a, $b);
    }

    public function testStepExecutionLifecycle(): void
    {
        $repo = new InMemoryJobRepository();
        $instance = $repo->createJobInstance('jobA', JobParameters::of(['run.id' => 1]));
        $exec = $repo->createJobExecution($instance, JobParameters::of(['run.id' => 1]));
        $step = $exec->createStepExecution('step1');
        $repo->add($step);
        self::assertNotNull($step->getId());

        $step->getExecutionContext()->put('cursor', 100);
        $repo->updateExecutionContext($step);

        self::assertNotNull($repo->getLastStepExecution($instance, 'step1'));
        self::assertSame(1, $repo->getStepExecutionCount($instance, 'step1'));
    }
}
