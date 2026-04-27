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

namespace Lemric\BatchProcessing\Listener;

use Closure;
use Lemric\BatchProcessing\Listener\Adapter\{
    ChunkListenerAdapter,
    HookRegistry,
    ItemProcessListenerAdapter,
    ItemReadListenerAdapter,
    ItemWriteListenerAdapter,
    JobExecutionListenerAdapter,
    SkipListenerAdapter,
    StepExecutionListenerAdapter,
};
use ReflectionClass;
use ReflectionMethod;

/**
 * Introspects a POJO for PHP attribute-annotated methods and produces listener adapters.
 *
 * Responsibilities are intentionally narrow:
 *  - reflection over the target,
 *  - delegation of attribute → hook resolution to {@see HookRegistry},
 *  - delegation of hook dispatch to dedicated adapters.
 *
 * Multiple hooks belonging to the same listener interface are aggregated into a
 * single adapter instance, so a target with both `@BeforeJob` and `@AfterJob`
 * yields exactly one {@see JobExecutionListenerAdapter}.
 */
final class StepListenerFactory
{
    /** @var array<string, class-string> map: hook group → adapter class */
    private const array ADAPTER_CLASSES = [
        HookRegistry::GROUP_JOB => JobExecutionListenerAdapter::class,
        HookRegistry::GROUP_STEP => StepExecutionListenerAdapter::class,
        HookRegistry::GROUP_CHUNK => ChunkListenerAdapter::class,
        HookRegistry::GROUP_ITEM_READ => ItemReadListenerAdapter::class,
        HookRegistry::GROUP_ITEM_PROCESS => ItemProcessListenerAdapter::class,
        HookRegistry::GROUP_ITEM_WRITE => ItemWriteListenerAdapter::class,
        HookRegistry::GROUP_SKIP => SkipListenerAdapter::class,
    ];

    /**
     * @return list<object>
     */
    public static function getListeners(object $target): array
    {
        $listeners = [];
        foreach (self::collectHooks($target) as $group => $hooks) {
            $class = self::ADAPTER_CLASSES[$group];
            $listeners[] = new $class($hooks);
        }

        return $listeners;
    }

    /**
     * @return array<string, array<string, Closure>>
     */
    private static function collectHooks(object $target): array
    {
        $hooksByGroup = [];
        $reflection = new ReflectionClass($target);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attribute) {
                $binding = HookRegistry::find($attribute->getName());
                if (null === $binding) {
                    continue;
                }
                $hooksByGroup[$binding->group][$binding->hook] = $method->getClosure($target);
            }
        }

        return $hooksByGroup;
    }
}
