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

namespace Lemric\BatchProcessing\Tests\Bridge\Laravel;

use Lemric\BatchProcessing\Bridge\Laravel\Queue\{RunJobQueueJob, RunStepJobMiddleware};
use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters};
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class RunStepJobMiddlewareTest extends TestCase
{
    public function testMarksExecutionAsFailedOnException(): void
    {
        $repository = new InMemoryJobRepository();
        $instance = $repository->createJobInstance('testJob', new JobParameters());
        $execution = $repository->createJobExecution($instance, new JobParameters());
        $execution->setStatus(BatchStatus::STARTED);
        $repository->updateJobExecution($execution);

        $executionId = $execution->getId();
        self::assertNotNull($executionId);

        $middleware = new RunStepJobMiddleware($repository);
        $queueJob = new RunJobQueueJob($executionId, 'testJob', time(), null);

        $this->expectException(RuntimeException::class);

        try {
            $middleware->handle($queueJob, function () {
                throw new RuntimeException('Queue worker failure');
            });
        } catch (RuntimeException $e) {
            // Verify the execution was marked as FAILED.
            $updated = $repository->getJobExecution($executionId);
            self::assertNotNull($updated);
            self::assertSame(BatchStatus::FAILED, $updated->getStatus());

            throw $e;
        }
    }

    public function testPassesThroughOnSuccess(): void
    {
        $middleware = new RunStepJobMiddleware();
        $called = false;

        $middleware->handle(new stdClass(), function () use (&$called) {
            $called = true;
        });

        self::assertTrue($called);
    }
}
