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

namespace Lemric\BatchProcessing\Bridge\Laravel\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Lemric\BatchProcessing\Explorer\{AbstractCachedJobExplorer, JobExplorerInterface};

/**
 * Native Laravel {@code Cache::store()} backed {@see JobExplorerInterface} decorator. Mirrors
 * the framework's PSR-6 {@see \Lemric\BatchProcessing\Explorer\CachedJobExplorer} but uses
 * the Illuminate cache repository directly so applications can pin a specific store.
 *
 * Usage in a service provider:
 *   $this->app->singleton(JobExplorerInterface::class, function ($app) {
 *       return new LaravelCacheJobExplorer(
 *           new SimpleJobExplorer($app->make(JobRepositoryInterface::class)),
 *           Cache::store('redis')
 *       );
 *   });
 */
final class LaravelCacheJobExplorer extends AbstractCachedJobExplorer
{
    private const string CACHE_PREFIX = 'batch_explorer.';

    public function __construct(
        JobExplorerInterface $delegate,
        private readonly CacheRepository $cache,
        int $ttlSeconds = 60,
    ) {
        parent::__construct($delegate, $ttlSeconds);
    }

    protected function remember(string $suffix, callable $factory): mixed
    {
        return $this->cache->remember(self::CACHE_PREFIX.$suffix, $this->ttlSeconds, Closure::fromCallable($factory));
    }
}
