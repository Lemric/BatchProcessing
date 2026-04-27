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

namespace Lemric\BatchProcessing\Bridge\Symfony\DependencyInjection;

use Lemric\BatchProcessing\Bridge\Symfony\Command\{AbandonJobCommand,
    CleanupCommand,
    HealthCommand,
    JobStatusCommand,
    LaunchJobCommand,
    ListJobExecutionsCommand,
    RestartJobCommand,
    StopJobCommand};
use Lemric\BatchProcessing\Bridge\Symfony\Messenger\{MessengerJobDispatcher, RunJobMessageHandler};
use Lemric\BatchProcessing\Explorer\{JobExplorerInterface, SimpleJobExplorer};
use Lemric\BatchProcessing\Launcher\{AsyncJobLauncher, JobLauncherInterface, SimpleJobLauncher};
use Lemric\BatchProcessing\Operator\{JobOperatorInterface, SimpleJobOperator};
use Lemric\BatchProcessing\Registry\{InMemoryJobRegistry, JobRegistryInterface};
use Lemric\BatchProcessing\Repository\{InMemoryJobRepository, JobRepositoryInterface, PdoJobRepository};
use Lemric\BatchProcessing\Security\{AsyncJobMessageSigner,
    JobExecutionAccessCheckerInterface,
    NoOpJobExecutionAccessChecker,
    SqlIdentifierValidator};
use Lemric\BatchProcessing\Transaction\{PdoTransactionManager, TransactionManagerInterface};
use PDO;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition, Reference};
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * `batch_processing` Symfony extension.
 *
 * Translates the YAML configuration tree (see {@see Configuration}) into a fully wired set of
 * services: registry, repository, launcher (sync or async via Symfony Messenger), explorer,
 * operator and CLI commands. Tags applied here are consumed by {@see Compiler\BatchJobPass}.
 */
final class BatchProcessingExtension extends Extension
{
    public function getAlias(): string
    {
        return 'batch_processing';
    }

    /**
     * @param array<int, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{table_prefix?: string, data_source?: string, async_launcher?: array{enabled?: bool, transport?: string, message_secret?: string|null}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        // --- core services -------------------------------------------------
        $tablePrefix = (string) ($config['table_prefix'] ?? 'batch_');
        SqlIdentifierValidator::assertValidTablePrefix($tablePrefix);
        $dataSource = (string) ($config['data_source'] ?? 'default');

        // When a Doctrine DBAL connection is available, use PdoJobRepository for production.
        $dbalConnectionId = 'doctrine.dbal.'.$dataSource.'_connection';
        if ('default' !== $dataSource || $container->hasDefinition($dbalConnectionId) || $container->hasAlias($dbalConnectionId)) {
            $pdoFactory = new Definition(PDO::class);
            $pdoFactory->setFactory([new Reference($dbalConnectionId), 'getNativeConnection']);
            $pdoFactory->setPublic(false);
            $container->setDefinition('lemric.batch.pdo', $pdoFactory);

            $container->register(JobRepositoryInterface::class, PdoJobRepository::class)
                ->setArguments([new Reference('lemric.batch.pdo'), $tablePrefix])
                ->setPublic(true);

            $container->register(TransactionManagerInterface::class, PdoTransactionManager::class)
                ->setArguments([new Reference('lemric.batch.pdo')])
                ->setPublic(true);
        } else {
            $container->register(JobRepositoryInterface::class, InMemoryJobRepository::class)
                ->setPublic(true);
        }

        $container->register(JobRegistryInterface::class, InMemoryJobRegistry::class)
            ->setPublic(true);

        $container->register(JobExplorerInterface::class, SimpleJobExplorer::class)
            ->setArguments([new Reference(JobRepositoryInterface::class)])
            ->setPublic(true);

        $launcherDefinition = $this->buildLauncher($container, $config);
        $container->setDefinition(JobLauncherInterface::class, $launcherDefinition)->setPublic(true);

        $container->register(JobOperatorInterface::class, SimpleJobOperator::class)
            ->setArguments([
                new Reference(JobLauncherInterface::class),
                new Reference(JobRepositoryInterface::class),
                new Reference(JobRegistryInterface::class),
            ])
            ->setPublic(true);

        $container->register(NoOpJobExecutionAccessChecker::class, NoOpJobExecutionAccessChecker::class)->setPublic(false);
        $container->setAlias(JobExecutionAccessCheckerInterface::class, NoOpJobExecutionAccessChecker::class)->setPublic(false);

        // --- console commands ---------------------------------------------
        $this->registerCommand($container, LaunchJobCommand::class, [
            new Reference(JobOperatorInterface::class),
            null,
            null,
            null,
            null,
            new Reference(JobExecutionAccessCheckerInterface::class),
        ]);
        $this->registerCommand($container, ListJobExecutionsCommand::class, [new Reference(JobExplorerInterface::class)]);
        $this->registerCommand($container, JobStatusCommand::class, [
            new Reference(JobExplorerInterface::class),
            new Reference(JobExecutionAccessCheckerInterface::class),
        ]);
        $this->registerCommand($container, StopJobCommand::class, [
            new Reference(JobOperatorInterface::class),
            new Reference(JobExecutionAccessCheckerInterface::class),
        ]);
        $this->registerCommand($container, RestartJobCommand::class, [
            new Reference(JobOperatorInterface::class),
            new Reference(JobExecutionAccessCheckerInterface::class),
        ]);
        $this->registerCommand($container, AbandonJobCommand::class, [
            new Reference(JobOperatorInterface::class),
            new Reference(JobExecutionAccessCheckerInterface::class),
        ]);
        $this->registerCommand($container, CleanupCommand::class, [
            new Reference(JobOperatorInterface::class),
        ]);
        $this->registerCommand($container, HealthCommand::class, [
            new Reference(JobExplorerInterface::class),
        ]);

        // --- messenger handler --------------------------------------------
        /** @var array<string, mixed> $asyncConfig */
        $asyncConfig = $config['async_launcher'] ?? [];
        if (true === ($asyncConfig['enabled'] ?? false)) {
            $secretRaw = $asyncConfig['message_secret'] ?? '';
            $messageSecret = is_string($secretRaw) ? $secretRaw : '';
            $messageTtl = $this->asyncMessageTtlSeconds($asyncConfig);
            $handler = $container->register(RunJobMessageHandler::class, RunJobMessageHandler::class)
                ->setArguments([
                    new Reference(JobRegistryInterface::class),
                    new Reference(JobRepositoryInterface::class),
                    new Reference(JobLauncherInterface::class.'.sync'),
                    $messageSecret,
                    $messageTtl,
                ])
                ->addTag('messenger.message_handler');
            $handler->setPublic(true);
        }
    }

    /**
     * @param array<string, mixed> $asyncConfig
     */
    private function asyncMessageTtlSeconds(array $asyncConfig): int
    {
        $raw = $asyncConfig['message_ttl_seconds'] ?? AsyncJobMessageSigner::DEFAULT_MAX_MESSAGE_AGE_SECONDS;
        $v = is_int($raw) ? $raw : (is_numeric($raw) ? (int) $raw : AsyncJobMessageSigner::DEFAULT_MAX_MESSAGE_AGE_SECONDS);

        return max(60, $v);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildLauncher(ContainerBuilder $container, array $config): Definition
    {
        $sync = new Definition(SimpleJobLauncher::class, [new Reference(JobRepositoryInterface::class)]);
        $sync->setPublic(true);
        $container->setDefinition(JobLauncherInterface::class.'.sync', $sync);

        /** @var array<string, mixed> $asyncLauncher */
        $asyncLauncher = is_array($config['async_launcher'] ?? null) ? $config['async_launcher'] : [];

        if (true !== ($asyncLauncher['enabled'] ?? false)) {
            return $sync;
        }

        $transport = is_scalar($asyncLauncher['transport'] ?? null) ? (string) $asyncLauncher['transport'] : 'async_batch';
        $secretRaw = $asyncLauncher['message_secret'] ?? '';
        $messageSecret = is_string($secretRaw) ? $secretRaw : '';

        $dispatcher = new Definition(MessengerJobDispatcher::class, [
            new Reference('messenger.bus.default', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            $transport,
            $messageSecret,
        ]);
        $dispatcher->setPublic(true);
        $container->setDefinition(MessengerJobDispatcher::class, $dispatcher);

        return new Definition(AsyncJobLauncher::class, [
            new Reference(JobRepositoryInterface::class),
            new Reference(MessengerJobDispatcher::class),
        ]);
    }

    /**
     * @param class-string $class
     */
    private function commandName(string $class): ?string
    {
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsCommand::class);
        if ([] === $attributes) {
            return null;
        }
        /** @var AsCommand $attr */
        $attr = $attributes[0]->newInstance();

        return $attr->name;
    }

    /**
     * @param class-string                       $class
     * @param list<Reference|string|object|null> $arguments
     */
    private function registerCommand(ContainerBuilder $container, string $class, array $arguments): void
    {
        $definition = new Definition($class, $arguments);
        $definition->setPublic(true);
        // AsCommand attribute is read by symfony/console; tag is required when autoconfiguration is off.
        $name = $this->commandName($class);
        $definition->addTag('console.command', null === $name ? [] : ['command' => $name]);
        $container->setDefinition($class, $definition);
    }
}
