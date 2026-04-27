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

namespace Lemric\BatchProcessing\Tests\Bridge\Symfony\Messenger;

use Lemric\BatchProcessing\Bridge\Symfony\Messenger\{RunJobMessage, RunJobMessageHandler};
use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters};
use Lemric\BatchProcessing\Exception\JobExecutionException;
use Lemric\BatchProcessing\Job\SimpleJob;
use Lemric\BatchProcessing\Launcher\SimpleJobLauncher;
use Lemric\BatchProcessing\Registry\InMemoryJobRegistry;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Security\AsyncJobMessageSigner;
use PHPUnit\Framework\TestCase;

final class RunJobMessageHandlerTest extends TestCase
{
    public function testHandlerExecutesJob(): void
    {
        $repository = new InMemoryJobRepository();
        $registry = new InMemoryJobRegistry();

        $tasklet = new class implements \Lemric\BatchProcessing\Step\TaskletInterface {
            public function execute(\Lemric\BatchProcessing\Domain\StepContribution $c, \Lemric\BatchProcessing\Chunk\ChunkContext $ctx): \Lemric\BatchProcessing\Step\RepeatStatus
            {
                return \Lemric\BatchProcessing\Step\RepeatStatus::FINISHED;
            }
        };
        $step = new \Lemric\BatchProcessing\Step\TaskletStep('step1', $repository, $tasklet, new \Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager());
        $job = new SimpleJob('testJob', $repository);
        $job->addStep($step);
        $registry->register('testJob', $job);

        $params = JobParameters::of(['run.id' => 1]);
        $instance = $repository->createJobInstance('testJob', $params);
        $execution = $repository->createJobExecution($instance, $params);

        $launcher = new SimpleJobLauncher($repository);

        $secret = 'unit-test-hmac-secret';
        $handler = new RunJobMessageHandler($registry, $repository, $launcher, $secret);
        $eid = $execution->getId() ?? 0;
        $issuedAt = time();
        $jobKey = $params->toJobKey();
        $message = new RunJobMessage($eid, 'testJob', $issuedAt, AsyncJobMessageSigner::sign($secret, $eid, 'testJob', $issuedAt, $jobKey), $jobKey);

        $handler($message);

        $executions = $repository->findJobExecutions($instance);
        self::assertCount(1, $executions);
        self::assertSame(BatchStatus::COMPLETED, $executions[0]->getStatus());
    }

    public function testHandlerRejectsExpiredMessage(): void
    {
        $repository = new InMemoryJobRepository();
        $registry = new InMemoryJobRegistry();
        $launcher = new SimpleJobLauncher($repository);
        $secret = 'unit-test-hmac-secret';
        $handler = new RunJobMessageHandler($registry, $repository, $launcher, $secret, 3600);
        $oldIssued = time() - 10_000;
        $message = new RunJobMessage(1, 'any', $oldIssued, AsyncJobMessageSigner::sign($secret, 1, 'any', $oldIssued, null), null);

        $this->expectException(JobExecutionException::class);
        $handler($message);
    }

    public function testHandlerRejectsParametersFingerprintMismatch(): void
    {
        $repository = new InMemoryJobRepository();
        $registry = new InMemoryJobRegistry();

        $tasklet = new class implements \Lemric\BatchProcessing\Step\TaskletInterface {
            public function execute(\Lemric\BatchProcessing\Domain\StepContribution $c, \Lemric\BatchProcessing\Chunk\ChunkContext $ctx): \Lemric\BatchProcessing\Step\RepeatStatus
            {
                return \Lemric\BatchProcessing\Step\RepeatStatus::FINISHED;
            }
        };
        $step = new \Lemric\BatchProcessing\Step\TaskletStep('step1', $repository, $tasklet, new \Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager());
        $job = new SimpleJob('testJob', $repository);
        $job->addStep($step);
        $registry->register('testJob', $job);

        $params = JobParameters::of(['run.id' => 1]);
        $instance = $repository->createJobInstance('testJob', $params);
        $execution = $repository->createJobExecution($instance, $params);

        $launcher = new SimpleJobLauncher($repository);
        $secret = 'unit-test-hmac-secret';
        $handler = new RunJobMessageHandler($registry, $repository, $launcher, $secret);
        $eid = $execution->getId() ?? 0;
        $issuedAt = time();
        $wrongKey = JobParameters::of(['run.id' => 999])->toJobKey();
        $message = new RunJobMessage($eid, 'testJob', $issuedAt, AsyncJobMessageSigner::sign($secret, $eid, 'testJob', $issuedAt, $wrongKey), $wrongKey);

        $this->expectException(JobExecutionException::class);
        $this->expectExceptionMessage('fingerprint');
        $handler($message);
    }

    public function testHandlerRejectsSignatureWhenSecretConfigured(): void
    {
        $repository = new InMemoryJobRepository();
        $registry = new InMemoryJobRegistry();

        $tasklet = new class implements \Lemric\BatchProcessing\Step\TaskletInterface {
            public function execute(\Lemric\BatchProcessing\Domain\StepContribution $c, \Lemric\BatchProcessing\Chunk\ChunkContext $ctx): \Lemric\BatchProcessing\Step\RepeatStatus
            {
                return \Lemric\BatchProcessing\Step\RepeatStatus::FINISHED;
            }
        };
        $step = new \Lemric\BatchProcessing\Step\TaskletStep('step1', $repository, $tasklet, new \Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager());
        $job = new SimpleJob('testJob', $repository);
        $job->addStep($step);
        $registry->register('testJob', $job);

        $params = JobParameters::of(['run.id' => 1]);
        $instance = $repository->createJobInstance('testJob', $params);
        $execution = $repository->createJobExecution($instance, $params);

        $launcher = new SimpleJobLauncher($repository);
        $secret = 'test-secret-key';
        $handler = new RunJobMessageHandler($registry, $repository, $launcher, $secret);
        $issuedAt = time();
        $jobKey = $params->toJobKey();
        $badSig = AsyncJobMessageSigner::sign('other-secret', $execution->getId() ?? 0, 'testJob', $issuedAt, $jobKey);
        $message = new RunJobMessage($execution->getId() ?? 0, 'testJob', $issuedAt, $badSig, $jobKey);

        $this->expectException(JobExecutionException::class);
        $handler($message);
    }

    public function testHandlerThrowsForMissingExecution(): void
    {
        $repository = new InMemoryJobRepository();
        $registry = new InMemoryJobRegistry();
        $launcher = new SimpleJobLauncher($repository);

        $secret = 'unit-test-hmac-secret';
        $handler = new RunJobMessageHandler($registry, $repository, $launcher, $secret);
        $issuedAt = time();
        $message = new RunJobMessage(999, 'missingJob', $issuedAt, AsyncJobMessageSigner::sign($secret, 999, 'missingJob', $issuedAt, null), null);

        $this->expectException(JobExecutionException::class);
        $handler($message);
    }
}
