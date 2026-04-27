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

namespace Lemric\BatchProcessing\Listener\Adapter;

use Lemric\BatchProcessing\Attribute\Listener as Attr;

/**
 * Single source of truth that maps listener attribute classes to a {@see HookBinding}.
 *
 * Adding support for a new attribute requires a single entry here (Open/Closed Principle).
 */
final class HookRegistry
{
    public const string GROUP_CHUNK = 'chunk';

    public const string GROUP_ITEM_PROCESS = 'itemProcess';

    public const string GROUP_ITEM_READ = 'itemRead';

    public const string GROUP_ITEM_WRITE = 'itemWrite';

    public const string GROUP_JOB = 'job';

    public const string GROUP_SKIP = 'skip';

    public const string GROUP_STEP = 'step';

    /** @var array<class-string, HookBinding>|null */
    private static ?array $bindings = null;

    public static function find(string $attributeFqcn): ?HookBinding
    {
        return self::bindings()[$attributeFqcn] ?? null;
    }

    /**
     * @return array<class-string, HookBinding>
     */
    private static function bindings(): array
    {
        return self::$bindings ??= [
            Attr\BeforeJob::class => new HookBinding(self::GROUP_JOB, 'beforeJob'),
            Attr\AfterJob::class => new HookBinding(self::GROUP_JOB, 'afterJob'),

            Attr\BeforeStep::class => new HookBinding(self::GROUP_STEP, 'beforeStep'),
            Attr\AfterStep::class => new HookBinding(self::GROUP_STEP, 'afterStep'),

            Attr\BeforeChunk::class => new HookBinding(self::GROUP_CHUNK, 'beforeChunk'),
            Attr\AfterChunk::class => new HookBinding(self::GROUP_CHUNK, 'afterChunk'),

            Attr\BeforeRead::class => new HookBinding(self::GROUP_ITEM_READ, 'beforeRead'),
            Attr\AfterRead::class => new HookBinding(self::GROUP_ITEM_READ, 'afterRead'),
            Attr\OnReadError::class => new HookBinding(self::GROUP_ITEM_READ, 'onReadError'),

            Attr\BeforeProcess::class => new HookBinding(self::GROUP_ITEM_PROCESS, 'beforeProcess'),
            Attr\AfterProcess::class => new HookBinding(self::GROUP_ITEM_PROCESS, 'afterProcess'),

            Attr\BeforeWrite::class => new HookBinding(self::GROUP_ITEM_WRITE, 'beforeWrite'),
            Attr\AfterWrite::class => new HookBinding(self::GROUP_ITEM_WRITE, 'afterWrite'),
            Attr\OnWriteError::class => new HookBinding(self::GROUP_ITEM_WRITE, 'onWriteError'),

            Attr\OnSkipInRead::class => new HookBinding(self::GROUP_SKIP, 'onSkipInRead'),
            Attr\OnSkipInProcess::class => new HookBinding(self::GROUP_SKIP, 'onSkipInProcess'),
            Attr\OnSkipInWrite::class => new HookBinding(self::GROUP_SKIP, 'onSkipInWrite'),
        ];
    }
}
