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

namespace Lemric\BatchProcessing\Bridge\Symfony;

use Lemric\BatchProcessing\Bridge\Symfony\DependencyInjection\BatchProcessingExtension;
use Lemric\BatchProcessing\Bridge\Symfony\DependencyInjection\Compiler\BatchJobPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony Bundle entrypoint for the Lemric Batch Processing library.
 *
 * Wires up:
 *  - {@see BatchProcessingExtension} for `batch_processing:` YAML configuration,
 *  - {@see BatchJobPass} which collects services tagged `batch.job`/`batch.item_*`
 *    into the {@see \Lemric\BatchProcessing\Registry\InMemoryJobRegistry}.
 */
final class BatchProcessingBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new BatchJobPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    public function getContainerExtension(): ExtensionInterface
    {
        $extension = $this->extension;
        if (!$extension instanceof ExtensionInterface) {
            $extension = new BatchProcessingExtension();
            $this->extension = $extension;
        }

        return $extension;
    }
}
