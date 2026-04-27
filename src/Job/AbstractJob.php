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

namespace Lemric\BatchProcessing\Job;

use DateTimeImmutable;
use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, JobExecution, JobParameters};
use Lemric\BatchProcessing\Event\{AfterJobEvent, BeforeJobEvent, JobFailedEvent};
use Lemric\BatchProcessing\Exception\{JobInterruptedException, JobParametersInvalidException, UnexpectedJobExecutionException};
use Lemric\BatchProcessing\Listener\CompositeListener;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Security\SensitiveDataSanitizer;
use Lemric\BatchProcessing\Step\{AbstractStep, StepInterface};
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\{LoggerAwareInterface, LoggerInterface, NullLogger};
use Throwable;

abstract class AbstractJob implements JobInterface, LoggerAwareInterface
{
    /**
     * Allows a fresh execution of an already-COMPLETED {@see JobInstance}.
     * When false (default), launching the same identifying parameters again will be rejected
     * by the launcher with an {@see \Lemric\BatchProcessing\Exception\JobInstanceAlreadyCompleteException}.
     */
    protected bool $allowStartIfComplete = false;

    protected ?EventDispatcherInterface $eventDispatcher = null;

    protected ?JobParametersIncrementerInterface $incrementer = null;

    protected ?JobInterruptionPolicyInterface $interruptionPolicy = null;

    protected CompositeListener $listeners;

    protected LoggerInterface $logger;

    protected bool $restartable = true;

    protected ?JobParametersValidatorInterface $validator = null;

    public function __construct(
        protected readonly string $name,
        protected readonly JobRepositoryInterface $jobRepository,
    ) {
        $this->listeners = new CompositeListener();
        $this->logger = new NullLogger();
    }

    public function execute(JobExecution $jobExecution): void
    {
        // Defensive pre-condition: refuse to execute a job against a JobExecution that has
        // already reached a terminal state. Launchers normally guard this, but a direct
        // {@see JobInterface::execute()} call still has to be safe.
        $current = $jobExecution->getStatus();
        if (BatchStatus::COMPLETED === $current || BatchStatus::ABANDONED === $current) {
            throw new UnexpectedJobExecutionException(sprintf('Job "%s" cannot be executed: JobExecution is already in terminal status %s.', $this->name, $current->value));
        }

        try {
            $this->validateParameters($jobExecution->getJobParameters());
        } catch (JobParametersInvalidException $e) {
            $jobExecution->setStatus(BatchStatus::FAILED);
            $jobExecution->setExitStatus(ExitStatus::$FAILED->addExitDescription($e->getMessage()));
            $jobExecution->addFailureException($e);
            $jobExecution->setEndTime(new DateTimeImmutable());
            $this->jobRepository->updateJobExecution($jobExecution);
            throw $e;
        }

        $jobExecution->setStartTime(new DateTimeImmutable());
        $jobExecution->setStatus(BatchStatus::STARTED);
        $this->jobRepository->updateJobExecution($jobExecution);

        try {
            $this->listeners->beforeJob($jobExecution);
            $this->dispatch(new BeforeJobEvent($jobExecution));

            $this->doExecute($jobExecution);

            // Determine final status from step outcomes (unless already set by doExecute).
            if (BatchStatus::STARTED === $jobExecution->getStatus()) {
                $finalStatus = BatchStatus::COMPLETED;
                $finalExit = ExitStatus::$COMPLETED;
                foreach ($jobExecution->getStepExecutions() as $step) {
                    $finalStatus = $finalStatus->upgradeTo($step->getStatus());
                    $finalExit = $finalExit->and($step->getExitStatus());
                }
                $jobExecution->setStatus($finalStatus);
                $jobExecution->setExitStatus($finalExit);
            }
        } catch (JobInterruptedException $e) {
            $jobExecution->setStatus(BatchStatus::STOPPED);
            $jobExecution->setExitStatus(ExitStatus::$STOPPED->addExitDescription($e->getMessage()));
            $jobExecution->addFailureException($e);
            $this->logger->info('Job interrupted: '.$e->getMessage(), ['job' => $this->name]);
        } catch (Throwable $e) {
            $jobExecution->setStatus(BatchStatus::FAILED);
            $jobExecution->setExitStatus(ExitStatus::$FAILED->addExitDescription($e->getMessage()));
            $jobExecution->addFailureException($e);
            $this->dispatch(new JobFailedEvent($jobExecution, $e));
            $this->logger->error('Job failed: '.SensitiveDataSanitizer::sanitize($e->getMessage()), ['job' => $this->name, 'exception' => $e]);
        } finally {
            $jobExecution->setEndTime(new DateTimeImmutable());

            try {
                $this->listeners->afterJob($jobExecution);
            } catch (Throwable $listenerEx) {
                $this->logger->warning('afterJob listener failed: '.$listenerEx->getMessage());
                $jobExecution->addFailureException($listenerEx);
            }

            $this->dispatch(new AfterJobEvent($jobExecution));
            $this->jobRepository->updateJobExecutionContext($jobExecution);
            $this->jobRepository->updateJobExecution($jobExecution);
        }
    }

    public function getEventDispatcher(): ?EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function getIncrementer(): ?JobParametersIncrementerInterface
    {
        return $this->incrementer;
    }

    public function getInterruptionPolicy(): ?JobInterruptionPolicyInterface
    {
        return $this->interruptionPolicy;
    }

    /**
     * Exposes the composite listener so that subclasses can propagate listeners further (e.g.
     * a {@see SimpleJob} forwarding step-level listeners to every {@see StepInterface} it owns).
     */
    public function getListeners(): CompositeListener
    {
        return $this->listeners;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isAllowStartIfComplete(): bool
    {
        return $this->allowStartIfComplete;
    }

    public function isRestartable(): bool
    {
        return $this->restartable;
    }

    public function registerListener(object $listener): void
    {
        $this->listeners->register($listener);
    }

    public function setAllowStartIfComplete(bool $allow): void
    {
        $this->allowStartIfComplete = $allow;
    }

    public function setEventDispatcher(?EventDispatcherInterface $dispatcher): void
    {
        $this->eventDispatcher = $dispatcher;
    }

    public function setIncrementer(?JobParametersIncrementerInterface $incrementer): void
    {
        $this->incrementer = $incrementer;
    }

    public function setInterruptionPolicy(?JobInterruptionPolicyInterface $policy): void
    {
        $this->interruptionPolicy = $policy;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setRestartable(bool $restartable): void
    {
        $this->restartable = $restartable;
    }

    public function setValidator(?JobParametersValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function validateParameters(JobParameters $parameters): void
    {
        $this->validator?->validate($parameters);
    }

    /**
     * Propagates logger, event dispatcher and listeners to the given step when it is an
     * {@see AbstractStep}. Called by {@see SimpleJob::addStep()} and {@see FlowJob::addStep()}.
     */
    protected function configureStep(StepInterface $step): void
    {
        if ($step instanceof AbstractStep) {
            $step->setLogger($this->logger);
            $step->setEventDispatcher($this->eventDispatcher);
            $step->getListeners()->registerAll($this->listeners->getListeners());
        }
    }

    protected function dispatch(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }

    abstract protected function doExecute(JobExecution $jobExecution): void;

    /**
     * Executes all registered split flows. Returns {@code true} if the job should stop
     * (either because stopping was requested or because a flow caused a failure).
     *
     * Shared between {@see SimpleJob} and {@see FlowJob} to eliminate code duplication.
     *
     * @param list<SplitFlow> $splitFlows
     */
    protected function executeSplitFlows(array $splitFlows, JobExecution $jobExecution): bool
    {
        foreach ($splitFlows as $splitFlow) {
            if ($jobExecution->isStopping()) {
                $jobExecution->setStatus(BatchStatus::STOPPED);

                return true;
            }

            $splitFlow->execute($jobExecution);

            if ($jobExecution->getStatus()->isUnsuccessful()) {
                return true;
            }
        }

        return false;
    }
}
