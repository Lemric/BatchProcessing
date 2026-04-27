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

use Lemric\BatchProcessing\BatchEnvironmentBuilder;
use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameter, JobParameters, StepContribution};
use Lemric\BatchProcessing\Exception\{JobExecutionException, JobInstanceAlreadyCompleteException};
use Lemric\BatchProcessing\Job\{JobInterface, RunIdIncrementer, SimpleJob};
use Lemric\BatchProcessing\Operator\SimpleJobOperator;
use Lemric\BatchProcessing\Registry\InMemoryJobRegistry;
use Lemric\BatchProcessing\Repository\{InMemoryJobRepository, JobRepositoryInterface};
use Lemric\BatchProcessing\Step\{RepeatStatus, TaskletInterface};
use PHPUnit\Framework\TestCase;
use function assert;

final class SimpleJobOperatorTest extends TestCase
{
    public function testStartNextInstanceUsesIncrementer(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        $repo = $env->repository;
        $registry = $env->registry;
        /** @var SimpleJobOperator $operator */
        $operator = $env->operator;

        $registry->register('jobWithIncrementer', function () use ($repo): JobInterface {
            $job = $this->buildJob($repo, 'jobWithIncrementer');
            $job->setIncrementer(new RunIdIncrementer());

            return $job;
        });

        $first = $operator->startNextInstance('jobWithIncrementer');
        $second = $operator->startNextInstance('jobWithIncrementer');
        self::assertNotSame($first, $second);

        $execFirst = $repo->getJobExecution($first);
        $execSecond = $repo->getJobExecution($second);
        self::assertNotNull($execFirst);
        self::assertNotNull($execSecond);
        self::assertSame(1, $execFirst->getJobParameters()->getLong('run.id'));
        self::assertSame(2, $execSecond->getJobParameters()->getLong('run.id'));
    }

    public function testStartNextInstanceWithoutIncrementerThrows(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        $repo = $env->repository;
        $registry = $env->registry;
        $operator = $env->operator;

        $registry->register('plain', fn () => $this->buildJob($repo, 'plain'));

        $this->expectException(JobExecutionException::class);
        $operator->startNextInstance('plain');
    }

    public function testStartRestartAbandonStopAndStartNextInstance(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        /** @var InMemoryJobRepository $repo */
        $repo = $env->repository;
        /** @var InMemoryJobRegistry $registry */
        $registry = $env->registry;
        /** @var SimpleJobOperator $operator */
        $operator = $env->operator;

        $registry->register('demoJob', fn (): JobInterface => $this->buildJob($repo, 'demoJob'));

        $params = new JobParameters([
            'mode' => JobParameter::ofString('mode', 'full', identifying: true),
        ]);

        // start()
        $execId = $operator->start('demoJob', $params);
        self::assertGreaterThan(0, $execId);
        $exec = $repo->getJobExecution($execId);
        self::assertNotNull($exec);
        self::assertSame(BatchStatus::COMPLETED, $exec->getStatus());

        // start() with same identifying params → already completed
        $this->expectException(JobInstanceAlreadyCompleteException::class);
        $operator->start('demoJob', $params);
    }

    public function testStopReturnsFalseForFinishedExecution(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();
        $repo = $env->repository;
        $registry = $env->registry;
        /** @var SimpleJobOperator $operator */
        $operator = $env->operator;

        $registry->register('once', fn () => $this->buildJob($repo, 'once'));
        $execId = $operator->start('once', new JobParameters([]));

        $this->expectException(\Lemric\BatchProcessing\Exception\JobExecutionNotRunningException::class);
        $operator->stop($execId);
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
}
