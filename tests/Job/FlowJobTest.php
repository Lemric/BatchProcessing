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

namespace Lemric\BatchProcessing\Tests\Job;

use Closure;
use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, JobExecution, JobParameters, StepContribution};
use Lemric\BatchProcessing\Job\{FlowDeciderInterface, FlowJob};
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Step\RepeatStatus;
use Lemric\BatchProcessing\Step\{TaskletInterface, TaskletStep};
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;

final class FlowJobTest extends TestCase
{
    private InMemoryJobRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryJobRepository();
    }

    public function testConditionalFlowWithDecider(): void
    {
        $log = [];
        $step1 = $this->taskletStep('step1', static function () use (&$log): RepeatStatus {
            $log[] = 'step1';

            return RepeatStatus::FINISHED;
        });
        $stepA = $this->taskletStep('stepA', static function () use (&$log): RepeatStatus {
            $log[] = 'stepA';

            return RepeatStatus::FINISHED;
        });
        $stepB = $this->taskletStep('stepB', static function () use (&$log): RepeatStatus {
            $log[] = 'stepB';

            return RepeatStatus::FINISHED;
        });

        $decider = $this->createStub(FlowDeciderInterface::class);
        $decider->method('decide')->willReturn('GO_B');

        $job = new FlowJob('flowJob', $this->repository);
        $job->setStartStep($step1);
        $job->addStep($stepA);
        $job->addStep($stepB);
        $job->setDecider('step1', $decider);
        $job->addTransition('step1', 'GO_A', 'stepA');
        $job->addTransition('step1', 'GO_B', 'stepB');

        $execution = $this->launch($job);

        self::assertSame(['step1', 'stepB'], $log);
        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
    }

    public function testEmptyFlowJob(): void
    {
        $job = new FlowJob('emptyJob', $this->repository);
        $execution = $this->launch($job);

        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
    }

    public function testFlowStopsWhenNoTransitionMatches(): void
    {
        $log = [];
        $step1 = $this->taskletStep('step1', static function () use (&$log): RepeatStatus {
            $log[] = 'step1';

            return RepeatStatus::FINISHED;
        });
        $step2 = $this->taskletStep('step2', static function () use (&$log): RepeatStatus {
            $log[] = 'step2';

            return RepeatStatus::FINISHED;
        });

        $job = new FlowJob('flowJob', $this->repository);
        $job->setStartStep($step1);
        $job->addStep($step2);

        $execution = $this->launch($job);

        self::assertSame(['step1'], $log);
        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
    }

    public function testLinearFlowExecutesStepsInOrder(): void
    {
        $log = [];
        $step1 = $this->taskletStep('step1', static function () use (&$log): RepeatStatus {
            $log[] = 'step1';

            return RepeatStatus::FINISHED;
        });
        $step2 = $this->taskletStep('step2', static function () use (&$log): RepeatStatus {
            $log[] = 'step2';

            return RepeatStatus::FINISHED;
        });

        $job = new FlowJob('flowJob', $this->repository);
        $job->setStartStep($step1);
        $job->addStep($step2);
        $job->addTransition('step1', ExitStatus::COMPLETED_CODE, 'step2');

        $execution = $this->launch($job);

        self::assertSame(['step1', 'step2'], $log);
        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
    }

    public function testWildcardTransition(): void
    {
        $log = [];
        $step1 = $this->taskletStep('step1', static function () use (&$log): RepeatStatus {
            $log[] = 'step1';

            return RepeatStatus::FINISHED;
        });
        $stepFallback = $this->taskletStep('fallback', static function () use (&$log): RepeatStatus {
            $log[] = 'fallback';

            return RepeatStatus::FINISHED;
        });

        $job = new FlowJob('flowJob', $this->repository);
        $job->setStartStep($step1);
        $job->addStep($stepFallback);
        $job->addTransition('step1', '*', 'fallback');

        $execution = $this->launch($job);

        self::assertSame(['step1', 'fallback'], $log);
        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());
    }

    private function launch(FlowJob $job): JobExecution
    {
        $params = new JobParameters([]);
        $instance = $this->repository->createJobInstance($job->getName(), $params);
        $execution = $this->repository->createJobExecution($instance, $params);
        $job->execute($execution);

        return $execution;
    }

    /**
     * @param Closure(): RepeatStatus $callback
     */
    private function taskletStep(string $name, Closure $callback): TaskletStep
    {
        $tasklet = new class($callback) implements TaskletInterface {
            /** @param Closure(): RepeatStatus $fn */
            public function __construct(private readonly Closure $fn)
            {
            }

            public function execute(StepContribution $contribution, ChunkContext $chunkContext): RepeatStatus
            {
                return ($this->fn)();
            }
        };

        return new TaskletStep($name, $this->repository, $tasklet, new ResourcelessTransactionManager());
    }
}
