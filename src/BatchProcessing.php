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

namespace Lemric\BatchProcessing;

use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Explorer\{JobExplorerInterface, SimpleJobExplorer};
use Lemric\BatchProcessing\Job\{JobBuilder, JobBuilderFactory};
use Lemric\BatchProcessing\Launcher\{AsyncJobLauncher, JobLauncherInterface, SimpleJobLauncher};
use Lemric\BatchProcessing\Operator\{JobOperatorInterface, SimpleJobOperator};
use Lemric\BatchProcessing\Registry\{InMemoryJobRegistry, JobRegistryInterface};
use Lemric\BatchProcessing\Repository\{InMemoryJobRepository, JobRepositoryInterface, PdoJobRepository, PdoJobRepositorySchema};
use Lemric\BatchProcessing\Step\{StepBuilder, StepBuilderFactory};
use Lemric\BatchProcessing\Transaction\{PdoTransactionManager, ResourcelessTransactionManager, TransactionManagerInterface};
use PDO;

/**
 * Static facade providing the most common entry points to the framework.
 *
 * In production, prefer wiring the building blocks (repository, transaction manager, factories)
 * through your DI container of choice.
 */
final class BatchProcessing
{
    /**
     * Same as {@see inMemory()} but the launcher is an {@see AsyncJobLauncher} that delegates
     * actual execution to the supplied dispatcher (e.g. Symfony Messenger / Laravel Queue).
     *
     * @param callable(int, string, JobParameters): void $dispatcher receives (jobExecutionId, jobName, parameters)
     *
     * @return array{
     *     repository: JobRepositoryInterface,
     *     transactionManager: TransactionManagerInterface,
     *     stepBuilderFactory: StepBuilderFactory,
     *     jobBuilderFactory: JobBuilderFactory,
     *     launcher: JobLauncherInterface,
     *     registry: JobRegistryInterface,
     *     operator: JobOperatorInterface,
     *     explorer: JobExplorerInterface
     * }
     */
    public static function async(callable $dispatcher, ?JobRepositoryInterface $repository = null, ?TransactionManagerInterface $transactionManager = null): array
    {
        $repo = $repository ?? new InMemoryJobRepository();
        $tx = $transactionManager ?? new ResourcelessTransactionManager();
        $launcher = new AsyncJobLauncher($repo, $dispatcher);

        return self::buildEnvironment($repo, $tx, $launcher);
    }

    /**
     * Builds a fully wired environment using an in-memory repository and a no-op transaction
     * manager. Useful for tests, scripts and quick experiments.
     *
     * @return array{
     *     repository: JobRepositoryInterface,
     *     transactionManager: TransactionManagerInterface,
     *     stepBuilderFactory: StepBuilderFactory,
     *     jobBuilderFactory: JobBuilderFactory,
     *     launcher: JobLauncherInterface,
     *     registry: JobRegistryInterface,
     *     operator: JobOperatorInterface,
     *     explorer: JobExplorerInterface
     * }
     */
    public static function inMemory(): array
    {
        $repo = new InMemoryJobRepository();
        $tx = new ResourcelessTransactionManager();
        $launcher = new SimpleJobLauncher($repo);

        return self::buildEnvironment($repo, $tx, $launcher);
    }

    public static function job(string $name, JobRepositoryInterface $repo): JobBuilder
    {
        return new JobBuilder($name, $repo);
    }

    /**
     * Builds a production-grade environment backed by PDO. Use {@see PdoJobRepositorySchema}
     * to provision the metadata tables when bootstrapping.
     *
     * @return array{
     *     repository: JobRepositoryInterface,
     *     transactionManager: TransactionManagerInterface,
     *     stepBuilderFactory: StepBuilderFactory,
     *     jobBuilderFactory: JobBuilderFactory,
     *     launcher: JobLauncherInterface,
     *     registry: JobRegistryInterface,
     *     operator: JobOperatorInterface,
     *     explorer: JobExplorerInterface
     * }
     */
    public static function pdo(PDO $pdo, string $tablePrefix = 'batch_'): array
    {
        $repo = new PdoJobRepository($pdo, $tablePrefix);
        $tx = new PdoTransactionManager($pdo);
        $launcher = new SimpleJobLauncher($repo);

        return self::buildEnvironment($repo, $tx, $launcher);
    }

    public static function step(string $name, JobRepositoryInterface $repo, ?TransactionManagerInterface $tx = null): StepBuilder
    {
        return new StepBuilder($name, $repo, $tx);
    }

    /**
     * @return array{
     *     repository: JobRepositoryInterface,
     *     transactionManager: TransactionManagerInterface,
     *     stepBuilderFactory: StepBuilderFactory,
     *     jobBuilderFactory: JobBuilderFactory,
     *     launcher: JobLauncherInterface,
     *     registry: JobRegistryInterface,
     *     operator: JobOperatorInterface,
     *     explorer: JobExplorerInterface
     * }
     */
    private static function buildEnvironment(
        JobRepositoryInterface $repo,
        TransactionManagerInterface $tx,
        JobLauncherInterface $launcher,
    ): array {
        $registry = new InMemoryJobRegistry();

        return [
            'repository' => $repo,
            'transactionManager' => $tx,
            'stepBuilderFactory' => new StepBuilderFactory($repo, $tx),
            'jobBuilderFactory' => new JobBuilderFactory($repo),
            'launcher' => $launcher,
            'registry' => $registry,
            'operator' => new SimpleJobOperator($launcher, $repo, $registry),
            'explorer' => new SimpleJobExplorer($repo),
        ];
    }
}
