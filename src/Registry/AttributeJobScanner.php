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

namespace Lemric\BatchProcessing\Registry;

use Lemric\BatchProcessing\Attribute\BatchJob;
use Lemric\BatchProcessing\Job\JobInterface;
use ReflectionClass;

/**
 * Scans a list of class names for the {@see BatchJob} attribute and registers them in a
 * {@see JobRegistryInterface}. Used by framework bridges (Symfony, Laravel) to perform
 * auto-discovery of batch jobs declared with the attribute.
 *
 * Example:
 *
 *     AttributeJobScanner::scan(
 *         [MyExportJob::class, MyImportJob::class],
 *         fn(string $class) => $container->get($class),
 *         $registry,
 *     );
 */
final class AttributeJobScanner
{
    /**
     * Inspects the given classes and registers each one annotated with {@see BatchJob}.
     * The job instance is created lazily through the supplied factory on first lookup.
     *
     * @param iterable<class-string>               $classes
     * @param callable(class-string): JobInterface $factory used to instantiate jobs on demand
     */
    public static function scan(iterable $classes, callable $factory, JobRegistryInterface $registry): int
    {
        $count = 0;
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(BatchJob::class);
            if ([] === $attributes) {
                continue;
            }
            /** @var BatchJob $attribute */
            $attribute = $attributes[0]->newInstance();
            $registry->register($attribute->name, static fn (): JobInterface => $factory($class));
            ++$count;
        }

        return $count;
    }
}
