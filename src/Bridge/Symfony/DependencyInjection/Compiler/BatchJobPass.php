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

namespace Lemric\BatchProcessing\Bridge\Symfony\DependencyInjection\Compiler;

use Lemric\BatchProcessing\Registry\{ContainerJobRegistry, InMemoryJobRegistry, JobRegistryInterface};
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition, Reference, ServiceLocator};

/**
 * Collects services tagged with `batch.job` and registers them in the
 * {@see JobRegistryInterface}. The job class FQCN is resolved through a Service Locator so that
 * jobs are instantiated lazily — only when the registry is asked to {@see JobRegistryInterface::getJob()}.
 *
 * Tag attributes:
 *   - `job_name` (optional): explicit name; defaults to {@see JobInterface::getName()} if missing.
 */
final class BatchJobPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(JobRegistryInterface::class)) {
            return;
        }

        $tagged = $container->findTaggedServiceIds('batch.job');
        if ([] === $tagged) {
            return;
        }

        // Build a service locator: name => Reference(serviceId).
        $references = [];
        foreach ($tagged as $serviceId => $tags) {
            /** @var list<array{job_name?: string}> $tags */
            foreach ($tags as $attributes) {
                $name = $attributes['job_name'] ?? $serviceId;
                $references[$name] = new Reference($serviceId);
            }
            // Mark service public so the locator can fetch it lazily.
            $container->getDefinition($serviceId)->setPublic(true);
        }

        $locator = new Definition(ServiceLocator::class, [$references]);
        $locator->addTag('container.service_locator');
        $locatorId = 'lemric.batch.job_locator';
        $container->setDefinition($locatorId, $locator);

        // Replace the default in-memory registry with the container-backed one.
        $registry = $container->getDefinition(JobRegistryInterface::class);
        if (InMemoryJobRegistry::class === $registry->getClass()) {
            $registry
                ->setClass(ContainerJobRegistry::class)
                ->setArguments([new Reference($locatorId), array_keys($references)]);
        }

        // Collect batch.item_reader / batch.item_processor / batch.item_writer tags and
        // register them into a dedicated item component locator for framework consumers.
        $itemRefs = [];
        foreach (['batch.item_reader', 'batch.item_processor', 'batch.item_writer'] as $tag) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $serviceId) {
                $container->getDefinition($serviceId)->setPublic(true);
                $itemRefs[$serviceId] = new Reference($serviceId);
            }
        }
        if ([] !== $itemRefs) {
            $itemLocator = new Definition(ServiceLocator::class, [$itemRefs]);
            $itemLocator->addTag('container.service_locator');
            $container->setDefinition('lemric.batch.item_locator', $itemLocator);
        }
    }
}
