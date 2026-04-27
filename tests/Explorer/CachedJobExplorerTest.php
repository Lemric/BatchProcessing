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

namespace Lemric\BatchProcessing\Tests\Explorer;

use Lemric\BatchProcessing\Domain\{JobInstance};
use Lemric\BatchProcessing\Explorer\{CachedJobExplorer, JobExplorerInterface};
use PHPUnit\Framework\TestCase;
use Psr\Cache\{CacheItemInterface, CacheItemPoolInterface};

final class CachedJobExplorerTest extends TestCase
{
    public function testCachesJobInstance(): void
    {
        $instance = new JobInstance(1, 'job', 'key');

        $delegate = $this->createMock(JobExplorerInterface::class);
        $delegate->expects(self::once())->method('getJobInstance')->with(1)->willReturn($instance);

        $item1 = $this->createCacheItem(false, null);
        $item2 = $this->createCacheItem(true, $instance);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')
            ->willReturnOnConsecutiveCalls($item1, $item2);
        $pool->expects(self::once())->method('save');

        $explorer = new CachedJobExplorer($delegate, $pool, 120);

        // First call: miss → delegate.
        $result1 = $explorer->getJobInstance(1);
        self::assertSame($instance, $result1);

        // Second call: hit → cache.
        $result2 = $explorer->getJobInstance(1);
        self::assertSame($instance, $result2);
    }

    public function testCachesJobNames(): void
    {
        $delegate = $this->createMock(JobExplorerInterface::class);
        $delegate->expects(self::once())->method('getJobNames')->willReturn(['job1', 'job2']);

        $item = $this->createCacheItem(false, null);
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects(self::once())->method('save');

        $explorer = new CachedJobExplorer($delegate, $pool);
        $names = $explorer->getJobNames();

        self::assertSame(['job1', 'job2'], $names);
    }

    public function testRunningExecutionsAreNeverCached(): void
    {
        $delegate = $this->createMock(JobExplorerInterface::class);
        $delegate->expects(self::exactly(2))->method('findRunningJobExecutions')->willReturn([]);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects(self::never())->method('getItem');

        $explorer = new CachedJobExplorer($delegate, $pool);

        $explorer->findRunningJobExecutions('job');
        $explorer->findRunningJobExecutions('job');
    }

    private function createCacheItem(bool $hit, mixed $value): CacheItemInterface
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn($hit);
        $item->method('get')->willReturn($value);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();

        return $item;
    }
}
