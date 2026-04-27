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

namespace Lemric\BatchProcessing\Tests\Bridge\Symfony;

use Lemric\BatchProcessing\Bridge\Symfony\BatchProcessingBundle;
use Lemric\BatchProcessing\Bridge\Symfony\Command\{
    JobStatusCommand,
    LaunchJobCommand,
    ListJobExecutionsCommand,
    RestartJobCommand,
    StopJobCommand,
};
use Lemric\BatchProcessing\Bridge\Symfony\Messenger\{MessengerJobDispatcher, RunJobMessageHandler};
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Job\{AbstractJob, JobInterface};
use Lemric\BatchProcessing\Launcher\{AsyncJobLauncher, JobLauncherInterface, SimpleJobLauncher};
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Lemric\BatchProcessing\Registry\{ContainerJobRegistry, JobRegistryInterface};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition};
use Symfony\Component\Messenger\MessageBus;

final class BatchProcessingBundleTest extends TestCase
{
    public function testAsyncLauncherIsWiredWhenEnabled(): void
    {
        $container = $this->compile([
            ['async_launcher' => [
                'enabled' => true,
                'transport' => 'batch_async',
                'message_secret' => 'symfony-bundle-async-test-secret',
            ]],
        ], registerMessenger: true);

        self::assertInstanceOf(AsyncJobLauncher::class, $container->get(JobLauncherInterface::class));
        self::assertInstanceOf(MessengerJobDispatcher::class, $container->get(MessengerJobDispatcher::class));
        self::assertInstanceOf(RunJobMessageHandler::class, $container->get(RunJobMessageHandler::class));
    }

    public function testSyncContainerHasCoreServices(): void
    {
        $container = $this->compile([]);

        self::assertInstanceOf(JobRepositoryInterface::class, $container->get(JobRepositoryInterface::class));
        self::assertInstanceOf(JobRegistryInterface::class, $container->get(JobRegistryInterface::class));
        self::assertInstanceOf(JobExplorerInterface::class, $container->get(JobExplorerInterface::class));
        self::assertInstanceOf(SimpleJobLauncher::class, $container->get(JobLauncherInterface::class));
        self::assertInstanceOf(JobOperatorInterface::class, $container->get(JobOperatorInterface::class));

        // Commands are registered as services
        self::assertTrue($container->has(LaunchJobCommand::class));
        self::assertTrue($container->has(ListJobExecutionsCommand::class));
        self::assertTrue($container->has(JobStatusCommand::class));
        self::assertTrue($container->has(StopJobCommand::class));
        self::assertTrue($container->has(RestartJobCommand::class));
    }

    public function testTaggedJobIsAddedToContainerRegistry(): void
    {
        $container = $this->compile([]);

        // Add a tagged job before compile — recompile manually for this scenario.
        $container = new ContainerBuilder();
        $bundle = new BatchProcessingBundle();
        $bundle->build($container);
        /** @var \Symfony\Component\DependencyInjection\Extension\ExtensionInterface $extension */
        $extension = $bundle->getContainerExtension();
        $extension->load([], $container);

        $jobDef = new Definition(StubJob::class);
        $jobDef->addTag('batch.job', ['job_name' => 'stubJob']);
        $container->setDefinition(StubJob::class, $jobDef);

        $container->compile();

        $registry = $container->get(JobRegistryInterface::class);
        self::assertInstanceOf(ContainerJobRegistry::class, $registry);
        self::assertTrue($registry->hasJob('stubJob'));
        self::assertSame('stubJob', $registry->getJob('stubJob')->getName());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function compile(array $configs, bool $registerMessenger = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $bundle = new BatchProcessingBundle();
        $bundle->build($container);
        /** @var \Symfony\Component\DependencyInjection\Extension\ExtensionInterface $extension */
        $extension = $bundle->getContainerExtension();
        $extension->load($configs, $container);

        if ($registerMessenger) {
            $bus = new Definition(MessageBus::class, [[]]);
            $bus->setPublic(true);
            $container->setDefinition('messenger.bus.default', $bus);
        }

        // EventDispatcher / messenger handler bus etc. are not required for compilation here.
        $container->compile();

        return $container;
    }
}

final class StubJob extends AbstractJob implements JobInterface
{
    public function __construct()
    {
        parent::__construct('stubJob', new \Lemric\BatchProcessing\Repository\InMemoryJobRepository());
    }

    protected function doExecute(\Lemric\BatchProcessing\Domain\JobExecution $jobExecution): void
    {
    }
}
