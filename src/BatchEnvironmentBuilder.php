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
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Launcher\{AsyncJobLauncher, JobLauncherInterface, SimpleJobLauncher};
use Lemric\BatchProcessing\Operator\{JobOperatorInterface, SimpleJobOperator};
use Lemric\BatchProcessing\Registry\{InMemoryJobRegistry, JobRegistryInterface};
use Lemric\BatchProcessing\Repository\{InMemoryJobRepository, JobRepositoryInterface, PdoJobRepository};
use Lemric\BatchProcessing\Step\StepBuilderFactory;
use Lemric\BatchProcessing\Transaction\{PdoTransactionManager,
    ResourcelessTransactionManager,
    TransactionManagerInterface};
use PDO;

/**
 * Builder (creational pattern) for constructing a fully wired {@see BatchEnvironment}.
 *
 * Every component of the environment is configurable via a dedicated `with*()` method.
 * Components that are not explicitly set will be resolved to sensible defaults in {@see build()}.
 *
 * Usage:
 *   $env = BatchEnvironmentBuilder::create()
 *       ->withRepository(new PdoJobRepository($pdo))
 *       ->withTransactionManager(new PdoTransactionManager($pdo))
 *       ->build();
 */
final class BatchEnvironmentBuilder
{
    private ?JobExplorerInterface $explorer = null;

    /** @var callable(JobRepositoryInterface): JobExplorerInterface|null */
    private $explorerFactory;

    private ?JobBuilderFactory $jobBuilderFactory = null;

    /** @var callable(JobRepositoryInterface): JobBuilderFactory|null */
    private $jobBuilderFactoryFactory;

    private ?JobLauncherInterface $launcher = null;

    /** @var callable(JobRepositoryInterface): JobLauncherInterface|null */
    private $launcherFactory;

    private ?JobOperatorInterface $operator = null;

    /** @var callable(JobLauncherInterface, JobRepositoryInterface, JobRegistryInterface): JobOperatorInterface|null */
    private $operatorFactory;

    private ?JobRegistryInterface $registry = null;

    /** @var callable(): JobRegistryInterface|null */
    private $registryFactory;

    private ?JobRepositoryInterface $repository = null;

    /** @var callable(): JobRepositoryInterface|null */
    private $repositoryFactory;

    private ?StepBuilderFactory $stepBuilderFactory = null;

    /** @var callable(JobRepositoryInterface, TransactionManagerInterface): StepBuilderFactory|null */
    private $stepBuilderFactoryFactory;

    private ?TransactionManagerInterface $transactionManager = null;

    /** @var callable(): TransactionManagerInterface|null */
    private $transactionManagerFactory;

    private function __construct()
    {
    }

    /**
     * Preconfigured builder with an async launcher.
     *
     * @param callable(int, string, JobParameters): void $dispatcher
     */
    public static function async(
        callable $dispatcher,
        ?JobRepositoryInterface $repository = null,
        ?TransactionManagerInterface $transactionManager = null,
    ): self {
        $repo = $repository ?? new InMemoryJobRepository();
        $tx = $transactionManager ?? new ResourcelessTransactionManager();

        return self::create()
            ->withRepository($repo)
            ->withTransactionManager($tx)
            ->withLauncher(new AsyncJobLauncher($repo, $dispatcher));
    }

    public function build(): BatchEnvironment
    {
        $repo = $this->resolveRepository();
        $tx = $this->resolveTransactionManager();
        $launcher = $this->resolveLauncher($repo);
        $registry = $this->resolveRegistry();

        return new BatchEnvironment(
            repository: $repo,
            transactionManager: $tx,
            stepBuilderFactory: $this->resolveStepBuilderFactory($repo, $tx),
            jobBuilderFactory: $this->resolveJobBuilderFactory($repo),
            launcher: $launcher,
            registry: $registry,
            operator: $this->resolveOperator($launcher, $repo, $registry),
            explorer: $this->resolveExplorer($repo),
        );
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Preconfigured builder for an in-memory environment (tests / scripts).
     */
    public static function inMemory(): self
    {
        return self::create()
            ->withRepository(new InMemoryJobRepository())
            ->withTransactionManager(new ResourcelessTransactionManager());
    }

    /**
     * Preconfigured builder backed by PDO (production).
     */
    public static function pdo(PDO $pdo, string $tablePrefix = 'batch_'): self
    {
        return self::create()
            ->withRepository(new PdoJobRepository($pdo, $tablePrefix))
            ->withTransactionManager(new PdoTransactionManager($pdo));
    }

    public function withExplorer(JobExplorerInterface $explorer): self
    {
        $this->explorer = $explorer;
        $this->explorerFactory = null;

        return $this;
    }

    /**
     * @param callable(JobRepositoryInterface): JobExplorerInterface $explorerFactory
     */
    public function withExplorerFactory(callable $explorerFactory): self
    {
        $this->explorerFactory = $explorerFactory;
        $this->explorer = null;

        return $this;
    }

    public function withJobBuilderFactory(JobBuilderFactory $jobBuilderFactory): self
    {
        $this->jobBuilderFactory = $jobBuilderFactory;
        $this->jobBuilderFactoryFactory = null;

        return $this;
    }

    /**
     * @param callable(JobRepositoryInterface): JobBuilderFactory $jobBuilderFactory
     */
    public function withJobBuilderFactoryFactory(callable $jobBuilderFactory): self
    {
        $this->jobBuilderFactoryFactory = $jobBuilderFactory;
        $this->jobBuilderFactory = null;

        return $this;
    }

    public function withLauncher(JobLauncherInterface $launcher): self
    {
        $this->launcher = $launcher;
        $this->launcherFactory = null;

        return $this;
    }

    /**
     * @param callable(JobRepositoryInterface): JobLauncherInterface $launcherFactory
     */
    public function withLauncherFactory(callable $launcherFactory): self
    {
        $this->launcherFactory = $launcherFactory;
        $this->launcher = null;

        return $this;
    }

    public function withOperator(JobOperatorInterface $operator): self
    {
        $this->operator = $operator;
        $this->operatorFactory = null;

        return $this;
    }

    /**
     * @param callable(JobLauncherInterface, JobRepositoryInterface, JobRegistryInterface): JobOperatorInterface $operatorFactory
     */
    public function withOperatorFactory(callable $operatorFactory): self
    {
        $this->operatorFactory = $operatorFactory;
        $this->operator = null;

        return $this;
    }

    public function withRegistry(JobRegistryInterface $registry): self
    {
        $this->registry = $registry;
        $this->registryFactory = null;

        return $this;
    }

    /**
     * @param callable(): JobRegistryInterface $registryFactory
     */
    public function withRegistryFactory(callable $registryFactory): self
    {
        $this->registryFactory = $registryFactory;
        $this->registry = null;

        return $this;
    }

    public function withRepository(JobRepositoryInterface $repository): self
    {
        $this->repository = $repository;
        $this->repositoryFactory = null;

        return $this;
    }

    /**
     * @param callable(): JobRepositoryInterface $repositoryFactory
     */
    public function withRepositoryFactory(callable $repositoryFactory): self
    {
        $this->repositoryFactory = $repositoryFactory;
        $this->repository = null;

        return $this;
    }

    public function withStepBuilderFactory(StepBuilderFactory $stepBuilderFactory): self
    {
        $this->stepBuilderFactory = $stepBuilderFactory;
        $this->stepBuilderFactoryFactory = null;

        return $this;
    }

    /**
     * @param callable(JobRepositoryInterface, TransactionManagerInterface): StepBuilderFactory $stepBuilderFactory
     */
    public function withStepBuilderFactoryFactory(callable $stepBuilderFactory): self
    {
        $this->stepBuilderFactoryFactory = $stepBuilderFactory;
        $this->stepBuilderFactory = null;

        return $this;
    }

    public function withTransactionManager(TransactionManagerInterface $transactionManager): self
    {
        $this->transactionManager = $transactionManager;
        $this->transactionManagerFactory = null;

        return $this;
    }

    /**
     * @param callable(): TransactionManagerInterface $transactionManagerFactory
     */
    public function withTransactionManagerFactory(callable $transactionManagerFactory): self
    {
        $this->transactionManagerFactory = $transactionManagerFactory;
        $this->transactionManager = null;

        return $this;
    }

    private function resolveExplorer(JobRepositoryInterface $repository): JobExplorerInterface
    {
        if (null !== $this->explorer) {
            return $this->explorer;
        }
        if (null !== $this->explorerFactory) {
            return ($this->explorerFactory)($repository);
        }

        return new SimpleJobExplorer($repository);
    }

    private function resolveJobBuilderFactory(JobRepositoryInterface $repository): JobBuilderFactory
    {
        if (null !== $this->jobBuilderFactory) {
            return $this->jobBuilderFactory;
        }
        if (null !== $this->jobBuilderFactoryFactory) {
            return ($this->jobBuilderFactoryFactory)($repository);
        }

        return new JobBuilderFactory($repository);
    }

    private function resolveLauncher(JobRepositoryInterface $repository): JobLauncherInterface
    {
        if (null !== $this->launcher) {
            return $this->launcher;
        }
        if (null !== $this->launcherFactory) {
            return ($this->launcherFactory)($repository);
        }

        return new SimpleJobLauncher($repository);
    }

    private function resolveOperator(
        JobLauncherInterface $launcher,
        JobRepositoryInterface $repository,
        JobRegistryInterface $registry,
    ): JobOperatorInterface {
        if (null !== $this->operator) {
            return $this->operator;
        }
        if (null !== $this->operatorFactory) {
            return ($this->operatorFactory)($launcher, $repository, $registry);
        }

        return new SimpleJobOperator($launcher, $repository, $registry);
    }

    private function resolveRegistry(): JobRegistryInterface
    {
        if (null !== $this->registry) {
            return $this->registry;
        }
        if (null !== $this->registryFactory) {
            return ($this->registryFactory)();
        }

        return new InMemoryJobRegistry();
    }

    private function resolveRepository(): JobRepositoryInterface
    {
        if (null !== $this->repository) {
            return $this->repository;
        }
        if (null !== $this->repositoryFactory) {
            return ($this->repositoryFactory)();
        }

        return new InMemoryJobRepository();
    }

    private function resolveStepBuilderFactory(
        JobRepositoryInterface $repository,
        TransactionManagerInterface $transactionManager,
    ): StepBuilderFactory {
        if (null !== $this->stepBuilderFactory) {
            return $this->stepBuilderFactory;
        }
        if (null !== $this->stepBuilderFactoryFactory) {
            return ($this->stepBuilderFactoryFactory)($repository, $transactionManager);
        }

        return new StepBuilderFactory($repository, $transactionManager);
    }

    private function resolveTransactionManager(): TransactionManagerInterface
    {
        if (null !== $this->transactionManager) {
            return $this->transactionManager;
        }
        if (null !== $this->transactionManagerFactory) {
            return ($this->transactionManagerFactory)();
        }

        return new ResourcelessTransactionManager();
    }
}
