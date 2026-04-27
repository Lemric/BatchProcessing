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

namespace Lemric\BatchProcessing\Tests\Operator;

use Lemric\BatchProcessing\{BatchEnvironment, BatchEnvironmentBuilder};
use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{JobParameters, StepContribution};
use Lemric\BatchProcessing\Exception\JobExecutionException;
use Lemric\BatchProcessing\Job\SimpleJob;
use Lemric\BatchProcessing\Operator\SimpleJobOperator;
use Lemric\BatchProcessing\Repository\{InMemoryJobRepository, JobRepositoryInterface};
use Lemric\BatchProcessing\Step\{RepeatStatus, TaskletInterface};
use PHPUnit\Framework\TestCase;
use function assert;

final class JobOperatorAdminMethodsTest extends TestCase
{
    public function testGetExecutions(): void
    {
        $env = $this->env();
        /** @var SimpleJobOperator $op */
        $op = $env->operator;
        /** @var InMemoryJobRepository $repo */
        $repo = $env->repository;

        $execId = $op->start('testJob', new JobParameters([]));
        $instance = $repo->getJobExecution($execId)?->getJobInstance();
        self::assertNotNull($instance);

        $executions = $op->getExecutions($instance->getId() ?? 0);
        self::assertContains($execId, $executions);
    }

    public function testGetJobInstances(): void
    {
        $env = $this->env();
        /** @var SimpleJobOperator $op */
        $op = $env->operator;

        $op->start('testJob', new JobParameters([]));
        $instances = $op->getJobInstances('testJob', 0, 10);
        self::assertNotEmpty($instances);
    }

    public function testGetJobNames(): void
    {
        $env = $this->env();
        /** @var SimpleJobOperator $op */
        $op = $env->operator;

        $names = $op->getJobNames();
        self::assertContains('testJob', $names);
        self::assertContains('otherJob', $names);
    }

    public function testGetParameters(): void
    {
        $env = $this->env();
        /** @var SimpleJobOperator $op */
        $op = $env->operator;

        $params = JobParameters::of(['key' => 'val']);
        $execId = $op->start('testJob', $params);
        $paramStr = $op->getParameters($execId);
        self::assertStringContainsString('key=val', $paramStr);
    }

    public function testGetParametsThrowsForMissing(): void
    {
        $env = $this->env();
        /** @var SimpleJobOperator $op */
        $op = $env->operator;

        $this->expectException(JobExecutionException::class);
        $op->getParameters(9999);
    }

    public function testGetRunningExecutions(): void
    {
        $env = $this->env();
        /** @var SimpleJobOperator $op */
        $op = $env->operator;

        // After completion, no running executions
        $op->start('testJob', new JobParameters([]));
        $running = $op->getRunningExecutions('testJob');
        self::assertEmpty($running);
    }

    public function testGetStepExecutionSummaries(): void
    {
        $env = $this->env();
        /** @var SimpleJobOperator $op */
        $op = $env->operator;

        $execId = $op->start('testJob', new JobParameters([]));
        $summaries = $op->getStepExecutionSummaries($execId);
        self::assertNotEmpty($summaries);
    }

    public function testGetSummary(): void
    {
        $env = $this->env();
        /** @var SimpleJobOperator $op */
        $op = $env->operator;

        $execId = $op->start('testJob', new JobParameters([]));
        $summary = $op->getSummary($execId);
        self::assertStringContainsString('testJob', $summary);
        self::assertStringContainsString('COMPLETED', $summary);
    }

    private function buildJob(JobRepositoryInterface $repo, string $name): SimpleJob
    {
        $tasklet = new class implements TaskletInterface {
            public function execute(StepContribution $contribution, ChunkContext $context): RepeatStatus
            {
                return RepeatStatus::FINISHED;
            }
        };
        $factory = new \Lemric\BatchProcessing\Step\StepBuilderFactory($repo);
        $step = $factory->get($name.'.step')->tasklet($tasklet)->build();

        $jobBuilder = new \Lemric\BatchProcessing\Job\JobBuilderFactory($repo)->get($name);
        $job = $jobBuilder->start($step)->build();
        assert($job instanceof SimpleJob);

        return $job;
    }

    private function env(): BatchEnvironment
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        $repo = $env->repository;
        $registry = $env->registry;

        $registry->register('testJob', fn () => $this->buildJob($repo, 'testJob'));
        $registry->register('otherJob', fn () => $this->buildJob($repo, 'otherJob'));

        return $env;
    }
}
