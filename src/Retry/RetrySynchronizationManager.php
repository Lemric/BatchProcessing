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

namespace Lemric\BatchProcessing\Retry;

/**
 * Thread/fiber-local holder for the current {@see RetryContext}.
 */
final class RetrySynchronizationManager
{
    private static ?RetryContext $context = null;

    public static function clear(): ?RetryContext
    {
        $old = self::$context;
        self::$context = null;

        return $old;
    }

    public static function getContext(): ?RetryContext
    {
        return self::$context;
    }

    public static function register(?RetryContext $context): ?RetryContext
    {
        $old = self::$context;
        self::$context = $context;

        return $old;
    }
}
