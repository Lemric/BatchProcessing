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

namespace Lemric\BatchProcessing\Tests\Chunk;

use Lemric\BatchProcessing\Chunk\{
    ChunkContext,
    CompositeCompletionPolicy,
    CountingCompletionPolicy,
    SimpleCompletionPolicy,
    TimeoutTerminationPolicy,
};
use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters, StepContribution, StepExecution};
use PHPUnit\Framework\TestCase;

final class CompletionPolicyTest extends TestCase
{
    public function testCompositeCompletionPolicyAndMode(): void
    {
        $policy = new CompositeCompletionPolicy([
            new SimpleCompletionPolicy(2),
            new SimpleCompletionPolicy(3),
        ], requireAll: true);

        $ctx = $this->createContext();
        $policy->start($ctx);
        $policy->update($ctx);
        $policy->update($ctx);
        self::assertFalse($policy->isComplete($ctx, 'item')); // First done but not second
        $policy->update($ctx);
        self::assertTrue($policy->isComplete($ctx, 'item')); // Both done
    }

    public function testCompositeCompletionPolicyOrMode(): void
    {
        $policy = new CompositeCompletionPolicy([
            new SimpleCompletionPolicy(100),
            new SimpleCompletionPolicy(2),
        ], requireAll: false);

        $ctx = $this->createContext();
        $policy->start($ctx);
        $policy->update($ctx);
        self::assertFalse($policy->isComplete($ctx, 'item'));
        $policy->update($ctx);
        self::assertTrue($policy->isComplete($ctx, 'item')); // Second policy triggers
    }

    public function testCountingCompletionPolicy(): void
    {
        $policy = new CountingCompletionPolicy(2);
        $ctx = $this->createContext();
        $policy->start($ctx);

        $policy->update($ctx);
        self::assertFalse($policy->isComplete($ctx, 'item'));
        $policy->update($ctx);
        self::assertTrue($policy->isComplete($ctx, 'item'));
    }

    public function testSimpleCompletionPolicyCompleteAfterN(): void
    {
        $policy = new SimpleCompletionPolicy(3);
        $ctx = $this->createContext();
        $policy->start($ctx);

        self::assertFalse($policy->isComplete($ctx, 'item'));
        $policy->update($ctx);
        self::assertFalse($policy->isComplete($ctx, 'item'));
        $policy->update($ctx);
        self::assertFalse($policy->isComplete($ctx, 'item'));
        $policy->update($ctx);
        self::assertTrue($policy->isComplete($ctx, 'item'));
    }

    public function testSimpleCompletionPolicyCompleteOnNull(): void
    {
        $policy = new SimpleCompletionPolicy(10);
        $ctx = $this->createContext();
        $policy->start($ctx);
        self::assertTrue($policy->isComplete($ctx, null));
    }

    public function testTimeoutTerminationPolicy(): void
    {
        $policy = new TimeoutTerminationPolicy(0.001); // 1ms
        $ctx = $this->createContext();
        $policy->start($ctx);

        usleep(2000); // 2ms
        self::assertTrue($policy->isComplete($ctx, 'item'));
    }

    private function createContext(): ChunkContext
    {
        $jobExecution = new JobExecution(null, new JobInstance(null, 'test', 'test'), new JobParameters());
        $stepExecution = new StepExecution('step', $jobExecution);

        return new ChunkContext(new StepContribution($stepExecution));
    }
}
