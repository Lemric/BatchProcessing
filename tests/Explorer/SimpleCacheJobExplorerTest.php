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

use Lemric\BatchProcessing\Domain\JobInstance;
use Lemric\BatchProcessing\Explorer\{JobExplorerInterface, SimpleCacheJobExplorer};
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class SimpleCacheJobExplorerTest extends TestCase
{
    public function testCachesJobInstanceOnMiss(): void
    {
        $instance = new JobInstance(1, 'job', 'key');

        $delegate = $this->createMock(JobExplorerInterface::class);
        $delegate->expects(self::once())->method('getJobInstance')->with(1)->willReturn($instance);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(static function ($key, $default = null) {
            return $default;
        });
        $cache->expects(self::once())->method('set');

        $explorer = new SimpleCacheJobExplorer($delegate, $cache, 30);
        $result = $explorer->getJobInstance(1);

        self::assertSame($instance, $result);
    }

    public function testReturnsCachedValueOnHit(): void
    {
        $instance = new JobInstance(1, 'job', 'key');

        $delegate = $this->createMock(JobExplorerInterface::class);
        $delegate->expects(self::never())->method('getJobInstance');

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn($instance); // hit

        $explorer = new SimpleCacheJobExplorer($delegate, $cache);
        $result = $explorer->getJobInstance(1);

        self::assertSame($instance, $result);
    }

    public function testRunningExecutionsBypassCache(): void
    {
        $delegate = $this->createMock(JobExplorerInterface::class);
        $delegate->expects(self::exactly(2))->method('findRunningJobExecutions')->willReturn([]);

        $cache = $this->createMock(CacheInterface::class);

        $explorer = new SimpleCacheJobExplorer($delegate, $cache);
        $explorer->findRunningJobExecutions('job');
        $explorer->findRunningJobExecutions('job');
    }

    public function testStepExecutionBypassesCache(): void
    {
        $delegate = $this->createMock(JobExplorerInterface::class);
        $delegate->expects(self::once())->method('getStepExecution')->willReturn(null);

        $cache = $this->createMock(CacheInterface::class);

        $explorer = new SimpleCacheJobExplorer($delegate, $cache);
        self::assertNull($explorer->getStepExecution(1, 2));
    }
}
