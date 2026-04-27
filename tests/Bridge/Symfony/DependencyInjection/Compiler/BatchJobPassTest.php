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

namespace Lemric\BatchProcessing\Tests\Bridge\Symfony\DependencyInjection\Compiler;

use Lemric\BatchProcessing\Bridge\Symfony\DependencyInjection\Compiler\BatchJobPass;
use Lemric\BatchProcessing\Job\SimpleJob;
use Lemric\BatchProcessing\Registry\{ContainerJobRegistry, InMemoryJobRegistry, JobRegistryInterface};
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition};

final class BatchJobPassTest extends TestCase
{
    public function testPassCollectsItemTags(): void
    {
        $container = new ContainerBuilder();
        $container->register(JobRegistryInterface::class, InMemoryJobRegistry::class);

        $jobDef = new Definition(SimpleJob::class);
        $jobDef->addTag('batch.job', ['job_name' => 'test']);
        $container->setDefinition('app.job.test', $jobDef);

        $readerDef = new Definition(stdClass::class);
        $readerDef->addTag('batch.item_reader');
        $container->setDefinition('app.reader.test', $readerDef);

        $pass = new BatchJobPass();
        $pass->process($container);

        self::assertTrue($container->hasDefinition('lemric.batch.item_locator'));
    }

    public function testPassIgnoresWhenNoRegistry(): void
    {
        $container = new ContainerBuilder();
        $pass = new BatchJobPass();
        $pass->process($container); // Should not throw
        self::assertFalse($container->hasDefinition('lemric.batch.item_locator'));
    }

    public function testPassIgnoresWhenNoTaggedServices(): void
    {
        $container = new ContainerBuilder();
        $container->register(JobRegistryInterface::class, InMemoryJobRegistry::class);

        $pass = new BatchJobPass();
        $pass->process($container);

        $registryDef = $container->getDefinition(JobRegistryInterface::class);
        self::assertSame(InMemoryJobRegistry::class, $registryDef->getClass());
    }

    public function testPassReplacesRegistryWithContainer(): void
    {
        $container = new ContainerBuilder();
        $container->register(JobRegistryInterface::class, InMemoryJobRegistry::class);

        $jobDef = new Definition(SimpleJob::class);
        $jobDef->addTag('batch.job', ['job_name' => 'myJob']);
        $container->setDefinition('app.job.my_job', $jobDef);

        $pass = new BatchJobPass();
        $pass->process($container);

        $registryDef = $container->getDefinition(JobRegistryInterface::class);
        self::assertSame(ContainerJobRegistry::class, $registryDef->getClass());
    }
}
