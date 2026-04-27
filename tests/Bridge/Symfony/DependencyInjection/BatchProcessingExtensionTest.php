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

namespace Lemric\BatchProcessing\Tests\Bridge\Symfony\DependencyInjection;

use Lemric\BatchProcessing\Bridge\Symfony\DependencyInjection\BatchProcessingExtension;
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Lemric\BatchProcessing\Registry\JobRegistryInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BatchProcessingExtensionTest extends TestCase
{
    public function testGetAlias(): void
    {
        $extension = new BatchProcessingExtension();
        self::assertSame('batch_processing', $extension->getAlias());
    }

    public function testLoadRegistersAllCoreServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new BatchProcessingExtension();

        $extension->load([], $container);

        self::assertTrue($container->hasDefinition(JobRepositoryInterface::class));
        self::assertTrue($container->hasDefinition(JobRegistryInterface::class));
        self::assertTrue($container->hasDefinition(JobExplorerInterface::class));
        self::assertTrue($container->hasDefinition(JobLauncherInterface::class));
        self::assertTrue($container->hasDefinition(JobOperatorInterface::class));
    }

    public function testLoadRegistersCommands(): void
    {
        $container = new ContainerBuilder();
        $extension = new BatchProcessingExtension();

        $extension->load([], $container);

        $commandClasses = [
            \Lemric\BatchProcessing\Bridge\Symfony\Command\LaunchJobCommand::class,
            \Lemric\BatchProcessing\Bridge\Symfony\Command\ListJobExecutionsCommand::class,
            \Lemric\BatchProcessing\Bridge\Symfony\Command\JobStatusCommand::class,
            \Lemric\BatchProcessing\Bridge\Symfony\Command\StopJobCommand::class,
            \Lemric\BatchProcessing\Bridge\Symfony\Command\RestartJobCommand::class,
        ];

        foreach ($commandClasses as $class) {
            self::assertTrue($container->hasDefinition($class), "Missing command: {$class}");
        }
    }
}
