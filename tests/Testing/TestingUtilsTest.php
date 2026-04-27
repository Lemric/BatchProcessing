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

namespace Lemric\BatchProcessing\Tests\Testing;

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{BatchStatus, StepContribution};
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Step\RepeatStatus;
use Lemric\BatchProcessing\Step\{TaskletInterface, TaskletStep};
use Lemric\BatchProcessing\Testing\{MetaDataInstanceFactory, StepRunner};
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;
use PHPUnit\Framework\TestCase;

final class TestingUtilsTest extends TestCase
{
    public function testMetaDataInstanceFactoryCreatesJobExecution(): void
    {
        $execution = MetaDataInstanceFactory::createJobExecution('testJob');

        self::assertSame('testJob', $execution->getJobInstance()->getJobName());
        self::assertNotNull($execution->getId());
    }
    // ── MetaDataInstanceFactory ─────────────────────────────────────────

    public function testMetaDataInstanceFactoryCreatesJobInstance(): void
    {
        MetaDataInstanceFactory::resetSequence();
        $instance = MetaDataInstanceFactory::createJobInstance('myJob');

        self::assertSame('myJob', $instance->getJobName());
        self::assertNotNull($instance->getId());
    }

    public function testMetaDataInstanceFactoryCreatesStepExecution(): void
    {
        $step = MetaDataInstanceFactory::createStepExecution('step1');

        self::assertSame('step1', $step->getStepName());
        self::assertNotNull($step->getId());
    }

    // ── StepRunner ──────────────────────────────────────────────────────

    public function testStepRunnerExecutesStepInIsolation(): void
    {
        $repository = new InMemoryJobRepository();

        $tasklet = new class implements TaskletInterface {
            public function execute(StepContribution $contribution, ChunkContext $chunkContext): RepeatStatus
            {
                $chunkContext->getStepExecution()->getExecutionContext()->put('done', true);

                return RepeatStatus::FINISHED;
            }
        };

        $step = new TaskletStep('testStep', $repository, $tasklet, new ResourcelessTransactionManager());
        $runner = new StepRunner($repository);
        $result = $runner->run($step);

        self::assertSame(BatchStatus::COMPLETED, $result->getStatus());
        self::assertTrue($result->getExecutionContext()->get('done'));
    }
}
