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

namespace Lemric\BatchProcessing\Tests\Exception;

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters, StepExecution};
use Lemric\BatchProcessing\Domain\{ExecutionContext, StepContribution};
use Lemric\BatchProcessing\Exception\{
    JobInterruptedException,
    NonTransientException,
    RetryInterruptedException,
    SkippableException,
    UnexpectedInputException,
    UnexpectedJobExecutionException,
    UnexpectedStepExecutionException,
};
use Lemric\BatchProcessing\Item\Reader\JsonLinesItemReader;
use Lemric\BatchProcessing\Job\SimpleJob;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Retry\Backoff\NoBackOffPolicy;
use Lemric\BatchProcessing\Retry\Policy\SimpleRetryPolicy;
use Lemric\BatchProcessing\Retry\RetryTemplate;
use Lemric\BatchProcessing\Skip\LimitCheckingItemSkipPolicy;
use Lemric\BatchProcessing\Step\{RepeatStatus, TaskletStep};
use Lemric\BatchProcessing\Step\TaskletInterface;
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WiredExceptionsTest extends TestCase
{
    public function testJobInterruptedExceptionIsConvertedToRetryInterrupted(): void
    {
        $template = new RetryTemplate(new SimpleRetryPolicy(5, [RuntimeException::class => true]), new NoBackOffPolicy());

        $this->expectException(RetryInterruptedException::class);
        $template->execute(static function (): void {
            throw new JobInterruptedException('stop requested');
        });
    }

    public function testJsonLinesReaderThrowsUnexpectedInputForScalarLine(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'jsonl');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, "42\n");

        $reader = new JsonLinesItemReader($tmp);
        $reader->open(new ExecutionContext());

        try {
            $this->expectException(UnexpectedInputException::class);
            $reader->read();
        } finally {
            $reader->close();
            @unlink($tmp);
        }
    }

    public function testNonTransientExceptionShortCircuitsRetry(): void
    {
        $policy = new SimpleRetryPolicy(5, [RuntimeException::class => true]);
        $template = new RetryTemplate($policy, new NoBackOffPolicy());

        $attempts = 0;
        $this->expectException(NonTransientException::class);
        try {
            $template->execute(static function () use (&$attempts): void {
                ++$attempts;
                throw new NonTransientException('fatal');
            });
        } finally {
            self::assertSame(1, $attempts, 'NonTransientException must not be retried.');
        }
    }

    public function testSkippableExceptionAlwaysSkippableWithinLimit(): void
    {
        $policy = new LimitCheckingItemSkipPolicy(3);
        self::assertTrue($policy->shouldSkip(new SkippableException('x'), 0));
        self::assertTrue($policy->shouldSkip(new SkippableException('x'), 2));
    }

    public function testUnexpectedJobExecutionExceptionOnTerminalStatus(): void
    {
        $repo = new InMemoryJobRepository();
        $instance = $repo->createJobInstance('job', new JobParameters());
        $jobExec = $repo->createJobExecution($instance, new JobParameters());
        $jobExec->setStatus(BatchStatus::COMPLETED);

        $job = new SimpleJob('job', $repo);

        $this->expectException(UnexpectedJobExecutionException::class);
        $job->execute($jobExec);
    }

    public function testUnexpectedStepExecutionExceptionOnTerminalStatus(): void
    {
        $repo = new InMemoryJobRepository();
        $instance = $repo->createJobInstance('job', new JobParameters());
        $jobExec = $repo->createJobExecution($instance, new JobParameters());

        $tasklet = new class implements TaskletInterface {
            public function execute(StepContribution $contribution, ChunkContext $chunkContext): RepeatStatus
            {
                return RepeatStatus::FINISHED;
            }
        };
        $step = new TaskletStep('s', $repo, $tasklet, new ResourcelessTransactionManager());

        $stepExec = new StepExecution('s', $jobExec);
        $stepExec->setStatus(BatchStatus::COMPLETED);

        $this->expectException(UnexpectedStepExecutionException::class);
        $step->execute($stepExec);
    }
}
