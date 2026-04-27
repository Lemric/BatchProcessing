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

use Lemric\BatchProcessing\Attribute\Listener as Attr;
use Lemric\BatchProcessing\Chunk\{Chunk, ChunkContext};
use Lemric\BatchProcessing\Domain\{ExitStatus, JobExecution, StepExecution};
use Lemric\BatchProcessing\Listener\Support\{
    ChunkListenerSupport,
    ItemProcessListenerSupport,
    ItemReadListenerSupport,
    ItemWriteListenerSupport,
    JobExecutionListenerSupport,
    SkipListenerSupport,
    StepExecutionListenerSupport,
};
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Introspects a POJO for PHP attribute-annotated methods and creates listener adapters.
 *
 * Uses the Support base classes to avoid anonymous class bloat.
 */
final class StepListenerFactory
{
    /** @var array<string, string> attribute class → hook name */
    private const array ADAPTER_MAP = [
        Attr\BeforeJob::class => 'beforeJob',
        Attr\AfterJob::class => 'afterJob',
        Attr\BeforeStep::class => 'beforeStep',
        Attr\AfterStep::class => 'afterStep',
        Attr\BeforeChunk::class => 'beforeChunk',
        Attr\AfterChunk::class => 'afterChunk',
        Attr\BeforeRead::class => 'beforeRead',
        Attr\AfterRead::class => 'afterRead',
        Attr\OnReadError::class => 'onReadError',
        Attr\BeforeProcess::class => 'beforeProcess',
        Attr\AfterProcess::class => 'afterProcess',
        Attr\BeforeWrite::class => 'beforeWrite',
        Attr\AfterWrite::class => 'afterWrite',
        Attr\OnWriteError::class => 'onWriteError',
        Attr\OnSkipInRead::class => 'onSkipInRead',
        Attr\OnSkipInProcess::class => 'onSkipInProcess',
        Attr\OnSkipInWrite::class => 'onSkipInWrite',
    ];

    /**
     * @return list<object>
     */
    public static function getListeners(object $target): array
    {
        $listeners = [];
        $ref = new ReflectionClass($target);

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attribute) {
                $attrName = $attribute->getName();
                if (!isset(self::ADAPTER_MAP[$attrName])) {
                    continue;
                }
                $hook = self::ADAPTER_MAP[$attrName];
                $methodName = $method->getName();
                $listener = self::createAdapter($target, $methodName, $hook);
                if (null !== $listener) {
                    $listeners[] = $listener;
                }
            }
        }

        return $listeners;
    }

    private static function createAdapter(object $target, string $method, string $hook): ?object
    {
        return match ($hook) {
            'beforeJob' => new class($target, $method) extends JobExecutionListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function beforeJob(JobExecution $je): void
                {
                    ($this->t)->{$this->m}($je);
                }
            },
            'afterJob' => new class($target, $method) extends JobExecutionListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function afterJob(JobExecution $je): void
                {
                    ($this->t)->{$this->m}($je);
                }
            },
            'beforeStep' => new class($target, $method) extends StepExecutionListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function beforeStep(StepExecution $se): void
                {
                    ($this->t)->{$this->m}($se);
                }
            },
            'afterStep' => new class($target, $method) extends StepExecutionListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function afterStep(StepExecution $se): ?ExitStatus
                {
                    ($this->t)->{$this->m}($se);

                    return null;
                }
            },
            'beforeChunk' => new class($target, $method) extends ChunkListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function beforeChunk(ChunkContext $c): void
                {
                    ($this->t)->{$this->m}($c);
                }
            },
            'afterChunk' => new class($target, $method) extends ChunkListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function afterChunk(ChunkContext $c): void
                {
                    ($this->t)->{$this->m}($c);
                }
            },
            'beforeRead' => new class($target, $method) extends ItemReadListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function beforeRead(): void
                {
                    ($this->t)->{$this->m}();
                }
            },
            'afterRead' => new class($target, $method) extends ItemReadListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function afterRead(mixed $item): void
                {
                    ($this->t)->{$this->m}($item);
                }
            },
            'onReadError' => new class($target, $method) extends ItemReadListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function onReadError(Throwable $e): void
                {
                    ($this->t)->{$this->m}($e);
                }
            },
            'beforeProcess' => new class($target, $method) extends ItemProcessListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function beforeProcess(mixed $item): void
                {
                    ($this->t)->{$this->m}($item);
                }
            },
            'afterProcess' => new class($target, $method) extends ItemProcessListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function afterProcess(mixed $item, mixed $result): void
                {
                    $args = [$item, $result];
                    ($this->t)->{$this->m}(...$args);
                }
            },
            'beforeWrite' => new class($target, $method) extends ItemWriteListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function beforeWrite(Chunk $items): void
                {
                    ($this->t)->{$this->m}($items);
                }
            },
            'afterWrite' => new class($target, $method) extends ItemWriteListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function afterWrite(Chunk $items): void
                {
                    ($this->t)->{$this->m}($items);
                }
            },
            'onWriteError' => new class($target, $method) extends ItemWriteListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function onWriteError(Throwable $e, Chunk $items): void
                {
                    ($this->t)->{$this->m}($e, $items);
                }
            },
            'onSkipInRead' => new class($target, $method) extends SkipListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function onSkipInRead(Throwable $e): void
                {
                    ($this->t)->{$this->m}($e);
                }
            },
            'onSkipInProcess' => new class($target, $method) extends SkipListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function onSkipInProcess(mixed $item, Throwable $e): void
                {
                    ($this->t)->{$this->m}($item, $e);
                }
            },
            'onSkipInWrite' => new class($target, $method) extends SkipListenerSupport {
                public function __construct(private readonly object $t, private readonly string $m)
                {
                }

                public function onSkipInWrite(mixed $item, Throwable $e): void
                {
                    ($this->t)->{$this->m}($item, $e);
                }
            },
            default => null,
        };
    }
}
