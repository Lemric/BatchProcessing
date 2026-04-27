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

namespace Lemric\BatchProcessing\Explorer;

use Psr\SimpleCache\CacheInterface;
use stdClass;

/**
 * PSR-16 (Simple Cache) decorator for {@see JobExplorerInterface}. Lighter alternative to
 * {@see CachedJobExplorer} for projects that don't use the full PSR-6 pool API.
 */
final class SimpleCacheJobExplorer extends AbstractCachedJobExplorer
{
    private const string CACHE_PREFIX = 'batch_explorer.';

    /** @var object Sentinel default for PSR-16 get() to distinguish real {@code false} from cache miss */
    private static ?object $cacheMissSentinel = null;

    public function __construct(
        JobExplorerInterface $delegate,
        private readonly CacheInterface $cache,
        int $ttlSeconds = 60,
    ) {
        parent::__construct($delegate, $ttlSeconds);
    }

    protected function remember(string $suffix, callable $factory): mixed
    {
        $key = self::CACHE_PREFIX.$suffix;

        if (null === self::$cacheMissSentinel) {
            self::$cacheMissSentinel = new stdClass();
        }
        $cached = $this->cache->get($key, self::$cacheMissSentinel);
        if (self::$cacheMissSentinel !== $cached) {
            return $cached;
        }

        $result = $factory();
        $this->cache->set($key, $result, $this->ttlSeconds);

        return $result;
    }
}
