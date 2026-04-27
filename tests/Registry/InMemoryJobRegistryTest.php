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

namespace Lemric\BatchProcessing\Tests\Registry;

use Lemric\BatchProcessing\Exception\BatchException;
use Lemric\BatchProcessing\Job\JobInterface;
use Lemric\BatchProcessing\Registry\InMemoryJobRegistry;
use PHPUnit\Framework\TestCase;

final class InMemoryJobRegistryTest extends TestCase
{
    public function testFactoryReturningNonJobThrows(): void
    {
        $registry = new InMemoryJobRegistry();
        $registry->register('badJob', static fn (): string => 'not a job');

        $this->expectException(BatchException::class);
        $registry->getJob('badJob');
    }

    public function testGetJobNames(): void
    {
        $registry = new InMemoryJobRegistry();
        $job = $this->createStub(JobInterface::class);

        $registry->register('job1', $job);
        $registry->register('job2', $job);

        $names = $registry->getJobNames();
        self::assertContains('job1', $names);
        self::assertContains('job2', $names);
        self::assertCount(2, $names);
    }

    public function testGetJobThrowsWhenNotRegistered(): void
    {
        $registry = new InMemoryJobRegistry();
        $this->expectException(BatchException::class);
        $registry->getJob('nonexistent');
    }

    public function testGetJobWithFactory(): void
    {
        $registry = new InMemoryJobRegistry();
        $job = $this->createStub(JobInterface::class);
        $factoryCalled = 0;

        $registry->register('lazyJob', static function () use ($job, &$factoryCalled): JobInterface {
            ++$factoryCalled;

            return $job;
        });

        self::assertTrue($registry->hasJob('lazyJob'));
        $result = $registry->getJob('lazyJob');
        self::assertSame($job, $result);
        self::assertSame(1, $factoryCalled);

        // Second call should use cached instance
        $registry->getJob('lazyJob');
        self::assertSame(1, $factoryCalled);
    }

    public function testHasJobReturnsFalseForUnknownJob(): void
    {
        $registry = new InMemoryJobRegistry();
        self::assertFalse($registry->hasJob('unknown'));
    }

    public function testRegisterAndGetJobInstance(): void
    {
        $registry = new InMemoryJobRegistry();
        $job = $this->createStub(JobInterface::class);
        $job->method('getName')->willReturn('myJob');

        $registry->register('myJob', $job);

        self::assertTrue($registry->hasJob('myJob'));
        self::assertSame($job, $registry->getJob('myJob'));
    }
}
