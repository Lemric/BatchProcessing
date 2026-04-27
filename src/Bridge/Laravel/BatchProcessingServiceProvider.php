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

namespace Lemric\BatchProcessing\Bridge\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\ServiceProvider;
use Lemric\BatchProcessing\Bridge\Laravel\Console\{
    JobStatusCommand,
    LaunchJobCommand,
    ListJobExecutionsCommand,
    RestartJobCommand,
    StopJobCommand,
};
use Lemric\BatchProcessing\Bridge\Laravel\Queue\QueueJobDispatcher;
use Lemric\BatchProcessing\Explorer\{JobExplorerInterface, SimpleJobExplorer};
use Lemric\BatchProcessing\Launcher\{AsyncJobLauncher, JobLauncherInterface, SimpleJobLauncher};
use Lemric\BatchProcessing\Operator\{JobOperatorInterface, SimpleJobOperator};
use Lemric\BatchProcessing\Registry\{InMemoryJobRegistry, JobRegistryInterface};
use Lemric\BatchProcessing\Repository\{InMemoryJobRepository, JobRepositoryInterface, PdoJobRepository};
use Lemric\BatchProcessing\Security\{AsyncJobMessageSigningRequirement, JobExecutionAccessCheckerInterface, NoOpJobExecutionAccessChecker, SqlIdentifierValidator};
use Lemric\BatchProcessing\Transaction\{PdoTransactionManager, TransactionManagerInterface};

/**
 * Laravel service provider wiring up the Lemric Batch Processing library.
 *
 * Publishes the `batch_processing.php` config file, registers core services as singletons
 * in the container, and exposes the artisan commands `batch:job:*`.
 */
final class BatchProcessingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $configPath = __DIR__.'/config/batch_processing.php';
        if (file_exists($configPath)) {
            $this->publishes([$configPath => $this->configPath()], 'batch-processing-config');
        }

        // Publish migration stubs.
        $migrationPath = __DIR__.'/database/migrations';
        if (is_dir($migrationPath)) {
            $migrationDest = $this->app->databasePath('migrations');
            $this->publishes([$migrationPath => $migrationDest], 'batch-processing-migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                LaunchJobCommand::class,
                ListJobExecutionsCommand::class,
                JobStatusCommand::class,
                StopJobCommand::class,
                RestartJobCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        if (!$this->app->bound(JobExecutionAccessCheckerInterface::class)) {
            $this->app->singleton(JobExecutionAccessCheckerInterface::class, NoOpJobExecutionAccessChecker::class);
        }

        $this->app->singleton(JobRepositoryInterface::class, function (Container $app): JobRepositoryInterface {
            $config = $this->getBatchConfig($app);

            $connection = $config['connection'] ?? null;
            if (null !== $connection && is_string($connection) && class_exists(\Illuminate\Database\DatabaseManager::class)) {
                /** @var \Illuminate\Database\DatabaseManager $db */
                $db = $app->make('db');
                $pdo = $db->connection($connection)->getPdo();
                $prefix = is_string($config['table_prefix'] ?? null) ? $config['table_prefix'] : 'batch_';
                SqlIdentifierValidator::assertValidTablePrefix($prefix);

                return new PdoJobRepository($pdo, $prefix);
            }

            return new InMemoryJobRepository();
        });

        $this->app->singleton(TransactionManagerInterface::class, function (Container $app): TransactionManagerInterface {
            $config = $this->getBatchConfig($app);

            $connection = $config['connection'] ?? null;
            if (null !== $connection && is_string($connection) && class_exists(\Illuminate\Database\DatabaseManager::class)) {
                /** @var \Illuminate\Database\DatabaseManager $db */
                $db = $app->make('db');

                return new PdoTransactionManager($db->connection($connection)->getPdo());
            }

            return new \Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager();
        });

        $this->app->singleton(JobRegistryInterface::class, InMemoryJobRegistry::class);

        $this->app->singleton(JobExplorerInterface::class, static function (Container $app): JobExplorerInterface {
            /** @var JobRepositoryInterface $repo */
            $repo = $app->make(JobRepositoryInterface::class);

            return new SimpleJobExplorer($repo);
        });

        $this->app->singleton(SimpleJobLauncher::class, static function (Container $app): SimpleJobLauncher {
            /** @var JobRepositoryInterface $repo */
            $repo = $app->make(JobRepositoryInterface::class);

            return new SimpleJobLauncher($repo);
        });

        $this->app->singleton(JobLauncherInterface::class, function (Container $app): JobLauncherInterface {
            $config = $this->getBatchConfig($app, 'batch_processing.async');

            if (true !== ($config['enabled'] ?? false)) {
                /** @var SimpleJobLauncher $sync */
                $sync = $app->make(SimpleJobLauncher::class);

                return $sync;
            }

            $messageSecret = $config['message_secret'] ?? null;
            AsyncJobMessageSigningRequirement::assertSecretConfiguredForAsync(
                true,
                $messageSecret,
                'batch_processing.async',
            );
            $messageSecret = is_string($messageSecret) ? $messageSecret : '';

            /** @var QueueFactory $queue */
            $queue = $app->make(QueueFactory::class);
            $connection = $config['connection'] ?? null;
            $queueName = $config['queue'] ?? null;
            $dispatcher = new QueueJobDispatcher(
                $queue,
                null === $connection ? null : (is_scalar($connection) ? (string) $connection : null),
                null === $queueName ? null : (is_scalar($queueName) ? (string) $queueName : null),
                $messageSecret,
            );
            /** @var JobRepositoryInterface $repo */
            $repo = $app->make(JobRepositoryInterface::class);

            return new AsyncJobLauncher($repo, $dispatcher);
        });

        $this->app->singleton(JobOperatorInterface::class, static function (Container $app): JobOperatorInterface {
            /** @var JobLauncherInterface $launcher */
            $launcher = $app->make(JobLauncherInterface::class);
            /** @var JobRepositoryInterface $repo */
            $repo = $app->make(JobRepositoryInterface::class);
            /** @var JobRegistryInterface $registry */
            $registry = $app->make(JobRegistryInterface::class);

            return new SimpleJobOperator($launcher, $repo, $registry);
        });
    }

    private function configPath(): string
    {
        return $this->app->configPath('batch_processing.php');
    }

    /**
     * Loads config from the container. Uses the specified config key.
     *
     * @return array<string, mixed>
     */
    private function getBatchConfig(Container $app, string $key = 'batch_processing'): array
    {
        if (!$app->has(ConfigRepository::class)) {
            return [];
        }

        /** @var ConfigRepository $configRepo */
        $configRepo = $app->make(ConfigRepository::class);

        /** @var array<string, mixed> $config */
        $config = (array) $configRepo->get($key, []);

        return $config;
    }
}
