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

use Psr\Cache\CacheItemPoolInterface;

/**
 * PSR-6 cache decorator for any {@see JobExplorerInterface} implementation. Caches read-only
 * query results (job instances, executions) with a configurable TTL. Useful for monitoring
 * dashboards that poll frequently but can tolerate slight staleness.
 *
 * Write-through: mutations are NOT cached — always call the underlying repository for updates.
 */
final class CachedJobExplorer extends AbstractCachedJobExplorer
{
    private const string CACHE_PREFIX = 'batch_explorer.';

    public function __construct(
        JobExplorerInterface $delegate,
        private readonly CacheItemPoolInterface $cache,
        int $ttlSeconds = 60,
    ) {
        parent::__construct($delegate, $ttlSeconds);
    }

    protected function remember(string $suffix, callable $factory): mixed
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX.$suffix);

        if ($item->isHit()) {
            return $item->get();
        }

        $result = $factory();
        $item->set($result)->expiresAfter($this->ttlSeconds);
        $this->cache->save($item);

        return $result;
    }
}
