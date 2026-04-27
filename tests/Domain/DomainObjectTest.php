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

namespace Lemric\BatchProcessing\Tests\Domain;

use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, JobExecution, JobInstance, JobParameters, StepContribution, StepExecution};
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DomainObjectTest extends TestCase
{
    public function testExitStatusAddDescription(): void
    {
        $status = new ExitStatus('FAILED', 'first');
        $result = $status->addExitDescription('second');
        self::assertSame('FAILED', $result->getExitCode());
        self::assertSame('first; second', $result->getExitDescription());
    }

    public function testExitStatusAddEmptyDescriptionReturnsIdentical(): void
    {
        $status = new ExitStatus('OK', 'desc');
        $result = $status->addExitDescription('');
        self::assertSame($status, $result);
    }

    public function testExitStatusAddSameDescriptionReturnsIdentical(): void
    {
        $status = new ExitStatus('OK', 'desc');
        $result = $status->addExitDescription('desc');
        self::assertSame($status, $result);
    }

    public function testExitStatusAndCombinesWithHigherSeverity(): void
    {
        $completed = ExitStatus::$COMPLETED;
        $failed = ExitStatus::$FAILED;
        $result = $completed->and($failed);
        self::assertSame(ExitStatus::FAILED_CODE, $result->getExitCode());
    }

    public function testExitStatusCompareTo(): void
    {
        self::assertLessThan(0, ExitStatus::$COMPLETED->compareTo(ExitStatus::$FAILED));
        self::assertGreaterThan(0, ExitStatus::$FAILED->compareTo(ExitStatus::$COMPLETED));
        self::assertSame(0, ExitStatus::$COMPLETED->compareTo(ExitStatus::$COMPLETED));
    }

    public function testExitStatusIsRunning(): void
    {
        self::assertTrue(ExitStatus::$EXECUTING->isRunning());
        self::assertTrue(ExitStatus::$UNKNOWN->isRunning());
        self::assertFalse(ExitStatus::$COMPLETED->isRunning());
    }

    public function testExitStatusReplaceExitCode(): void
    {
        $status = new ExitStatus('ORIGINAL', 'desc');
        $replaced = $status->replaceExitCode('REPLACED');
        self::assertSame('REPLACED', $replaced->getExitCode());
        self::assertSame('desc', $replaced->getExitDescription());
    }
    // ── ExitStatus ─────────────────────────────────────────────────────

    public function testExitStatusToString(): void
    {
        self::assertSame('COMPLETED', (string) ExitStatus::$COMPLETED);
        $withDesc = new ExitStatus('CUSTOM', 'some description');
        self::assertSame('CUSTOM: some description', (string) $withDesc);
    }

    public function testJobExecutionAggregatesFailureExceptions(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));
        $jobExecution->addFailureException(new RuntimeException('job'));

        $step = new StepExecution('s1', $jobExecution);
        $step->addFailureException(new RuntimeException('step'));

        $all = $jobExecution->getAllFailureExceptions();
        self::assertCount(2, $all);
    }

    // ── JobExecution ────────────────────────────────────────────────────

    public function testJobExecutionStopSetsStoppingStatus(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));

        self::assertFalse($jobExecution->isStopping());
        $jobExecution->stop();
        self::assertTrue($jobExecution->isStopping());
        self::assertSame(BatchStatus::STOPPING, $jobExecution->getStatus());
    }

    public function testJobExecutionStopSetsTerminateOnlyOnSteps(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));
        $step = new StepExecution('s1', $jobExecution);

        self::assertFalse($step->isTerminateOnly());
        $jobExecution->stop();
        self::assertTrue($step->isTerminateOnly());
    }

    public function testJobExecutionUpgradeStatus(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));

        $jobExecution->upgradeStatus(BatchStatus::STARTED);
        self::assertSame(BatchStatus::STARTED, $jobExecution->getStatus());

        // Upgrading to a lower-severity status should not downgrade
        $jobExecution->upgradeStatus(BatchStatus::STARTING);
        self::assertSame(BatchStatus::STARTED, $jobExecution->getStatus());
    }

    // ── JobInstance ─────────────────────────────────────────────────────

    public function testJobInstance(): void
    {
        $instance = new JobInstance(42, 'myJob', 'myKey');
        self::assertSame(42, $instance->getId());
        self::assertSame('myJob', $instance->getJobName());
        self::assertSame('myKey', $instance->getJobKey());
    }

    // ── StepContribution ────────────────────────────────────────────────

    public function testStepContributionApplyFoldsIntoStepExecution(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));
        $stepExec = new StepExecution('step1', $jobExecution);

        $contribution = new StepContribution($stepExec);
        $contribution->incrementReadCount(3);
        $contribution->incrementWriteCount(2);
        $contribution->incrementFilterCount(1);
        $contribution->incrementReadSkipCount();
        $contribution->incrementProcessSkipCount();
        $contribution->incrementWriteSkipCount();

        self::assertSame(3, $contribution->getReadCount());
        self::assertSame(2, $contribution->getWriteCount());
        self::assertSame(1, $contribution->getFilterCount());
        self::assertSame(1, $contribution->getReadSkipCount());
        self::assertSame(1, $contribution->getProcessSkipCount());
        self::assertSame(1, $contribution->getWriteSkipCount());

        $contribution->apply();

        self::assertSame(3, $stepExec->getReadCount());
        self::assertSame(2, $stepExec->getWriteCount());
        self::assertSame(1, $stepExec->getFilterCount());
        self::assertSame(1, $stepExec->getReadSkipCount());
        self::assertSame(1, $stepExec->getProcessSkipCount());
        self::assertSame(1, $stepExec->getWriteSkipCount());
    }

    public function testStepContributionExitStatus(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));
        $stepExec = new StepExecution('step1', $jobExecution);

        $contribution = new StepContribution($stepExec);
        self::assertSame(ExitStatus::EXECUTING_CODE, $contribution->getExitStatus()->getExitCode());

        $contribution->setExitStatus(ExitStatus::$COMPLETED);
        self::assertSame(ExitStatus::COMPLETED_CODE, $contribution->getExitStatus()->getExitCode());
    }

    public function testStepExecutionFailureExceptions(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));
        $stepExec = new StepExecution('step1', $jobExecution);

        $ex = new RuntimeException('test');
        $stepExec->addFailureException($ex);
        self::assertCount(1, $stepExec->getFailureExceptions());
        self::assertSame($ex, $stepExec->getFailureExceptions()[0]);
    }

    public function testStepExecutionSkipCount(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));
        $stepExec = new StepExecution('step1', $jobExecution);

        $stepExec->incrementReadSkipCount();
        $stepExec->incrementProcessSkipCount();
        $stepExec->incrementWriteSkipCount();
        self::assertSame(3, $stepExec->getSkipCount());
    }

    // ── StepExecution ───────────────────────────────────────────────────

    public function testStepExecutionSummary(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));
        $stepExec = new StepExecution('myStep', $jobExecution);

        $summary = $stepExec->getSummary();
        self::assertStringContainsString('myStep', $summary);
        self::assertStringContainsString('STARTING', $summary);
    }

    public function testStepExecutionTerminateOnly(): void
    {
        $jobInstance = new JobInstance(1, 'testJob', 'testKey');
        $jobExecution = new JobExecution(1, $jobInstance, new JobParameters([]));
        $stepExec = new StepExecution('step1', $jobExecution);

        self::assertFalse($stepExec->isTerminateOnly());
        $stepExec->setTerminateOnly();
        self::assertTrue($stepExec->isTerminateOnly());
    }
}
