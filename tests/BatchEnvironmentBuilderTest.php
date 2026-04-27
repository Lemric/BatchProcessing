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

namespace Lemric\BatchProcessing\Tests;

use Lemric\BatchProcessing\{BatchEnvironmentBuilder, BatchProcessing};
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Explorer\SimpleJobExplorer;
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Lemric\BatchProcessing\Operator\SimpleJobOperator;
use Lemric\BatchProcessing\Registry\InMemoryJobRegistry;
use Lemric\BatchProcessing\Repository\{InMemoryJobRepository, JobRepositoryInterface};
use Lemric\BatchProcessing\Step\StepBuilderFactory;
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BatchEnvironmentBuilderTest extends TestCase
{
    public function testBatchProcessingFacadeSupportsConfigurableEnvironmentFactory(): void
    {
        $env = BatchProcessing::environment(
            static fn (BatchEnvironmentBuilder $builder): BatchEnvironmentBuilder => $builder->withRepository(new InMemoryJobRepository()),
        );

        self::assertInstanceOf(InMemoryJobRepository::class, $env->repository);
    }

    public function testBuilderFactoriesAllowFullWiringCustomization(): void
    {
        $repo = new InMemoryJobRepository();
        $tx = new ResourcelessTransactionManager();
        $launcher = new class implements JobLauncherInterface {
            public function run(\Lemric\BatchProcessing\Job\JobInterface $job, JobParameters $parameters): \Lemric\BatchProcessing\Domain\JobExecution
            {
                throw new RuntimeException('not used in this test');
            }
        };
        $registry = new InMemoryJobRegistry();

        $env = BatchEnvironmentBuilder::create()
            ->withRepositoryFactory(static fn (): JobRepositoryInterface => $repo)
            ->withTransactionManagerFactory(static fn () => $tx)
            ->withLauncherFactory(static fn (JobRepositoryInterface $resolvedRepo): JobLauncherInterface => $resolvedRepo === $repo ? $launcher : throw new RuntimeException('repo mismatch'))
            ->withRegistryFactory(static fn (): InMemoryJobRegistry => $registry)
            ->withOperatorFactory(
                static fn (JobLauncherInterface $resolvedLauncher, JobRepositoryInterface $resolvedRepo, \Lemric\BatchProcessing\Registry\JobRegistryInterface $resolvedRegistry): SimpleJobOperator => new SimpleJobOperator($resolvedLauncher, $resolvedRepo, $resolvedRegistry),
            )
            ->withExplorerFactory(static fn (JobRepositoryInterface $resolvedRepo): SimpleJobExplorer => new SimpleJobExplorer($resolvedRepo))
            ->build();

        self::assertSame($repo, $env->repository);
        self::assertSame($tx, $env->transactionManager);
        self::assertSame($launcher, $env->launcher);
        self::assertSame($registry, $env->registry);
        self::assertInstanceOf(SimpleJobOperator::class, $env->operator);
        self::assertInstanceOf(SimpleJobExplorer::class, $env->explorer);
    }

    public function testBuilderSupportsFluentMutableConfiguration(): void
    {
        $base = BatchEnvironmentBuilder::create();
        $repo = new InMemoryJobRepository();

        $configured = $base->withRepository($repo);

        self::assertSame($base, $configured);
        self::assertSame($repo, $base->build()->repository);
    }

    public function testEnvironmentCanBeReconfiguredViaToBuilder(): void
    {
        $env = BatchEnvironmentBuilder::inMemory()->build();

        $reconfigured = $env->toBuilder()
            ->withStepBuilderFactoryFactory(
                static fn (JobRepositoryInterface $repo, \Lemric\BatchProcessing\Transaction\TransactionManagerInterface $tx): StepBuilderFactory => new StepBuilderFactory($repo, $tx),
            )
            ->build();

        self::assertSame($env->repository, $reconfigured->repository);
        self::assertSame($env->transactionManager, $reconfigured->transactionManager);
    }

    public function testJobBuilderFactoryFactoryIsAppliedDuringBuild(): void
    {
        $repo = new InMemoryJobRepository();
        $factory = new JobBuilderFactory($repo);

        $env = BatchEnvironmentBuilder::create()
            ->withRepository($repo)
            ->withJobBuilderFactoryFactory(
                static fn (JobRepositoryInterface $resolvedRepo): JobBuilderFactory => $resolvedRepo === $repo ? $factory : throw new RuntimeException('job builder repo mismatch'),
            )
            ->build();

        self::assertSame($factory, $env->jobBuilderFactory);
    }
}
