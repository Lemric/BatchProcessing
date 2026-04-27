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

namespace Lemric\BatchProcessing\Step;

use DateTimeImmutable;
use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, StepExecution};
use Lemric\BatchProcessing\Event\{AfterStepEvent, BeforeStepEvent, StepFailedEvent};
use Lemric\BatchProcessing\Exception\{JobInterruptedException, StartLimitExceededException, UnexpectedStepExecutionException};
use Lemric\BatchProcessing\Listener\CompositeListener;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\{LoggerAwareInterface, LoggerInterface, NullLogger};
use Throwable;

use const PHP_INT_MAX;

/**
 * Template Method base class implementing the standard step lifecycle:
 *  1. update status to STARTED, persist
 *  2. dispatch beforeStep listeners / events
 *  3. delegate to {@see doExecute()}
 *  4. update status / exit status, persist
 *  5. dispatch afterStep listeners / events (may upgrade exit status)
 *  6. log and re-throw on failure (status → FAILED, exit → FAILED)
 */
abstract class AbstractStep implements StepInterface, LoggerAwareInterface
{
    protected bool $allowStartIfComplete = false;

    protected ?EventDispatcherInterface $eventDispatcher = null;

    protected CompositeListener $listeners;

    protected LoggerInterface $logger;

    protected int $startLimit = PHP_INT_MAX;

    public function __construct(
        protected readonly string $name,
        protected readonly JobRepositoryInterface $jobRepository,
    ) {
        $this->listeners = new CompositeListener();
        $this->logger = new NullLogger();
    }

    public function execute(StepExecution $stepExecution): void
    {
        // Defensive pre-condition: a step instance must not be executed against a
        // {@see StepExecution} that has already reached a terminal state, unless explicitly
        // allowed via {@see setAllowStartIfComplete()}. This catches programmer errors such as
        // re-running a completed execution from a custom launcher.
        $current = $stepExecution->getStatus();
        if (
            (BatchStatus::COMPLETED === $current || BatchStatus::ABANDONED === $current)
            && !$this->allowStartIfComplete
        ) {
            throw new UnexpectedStepExecutionException(sprintf('Step "%s" cannot be executed: StepExecution is already in terminal status %s.', $this->name, $current->value));
        }

        // Enforce startLimit: count how many times this step has been started within
        // the same JobInstance and reject if the limit is exceeded.
        $jobInstance = $stepExecution->getJobExecution()->getJobInstance();
        $startCount = $this->jobRepository->getStepExecutionCount($jobInstance, $this->name);
        if ($startCount >= $this->startLimit) {
            throw new StartLimitExceededException(sprintf('Step "%s" has been started %d times, exceeding the start limit of %d.', $this->name, $startCount, $this->startLimit));
        }

        $stepExecution->setStartTime(new DateTimeImmutable());
        $stepExecution->setStatus(BatchStatus::STARTED);
        $this->jobRepository->add($stepExecution);

        $exitStatus = ExitStatus::$EXECUTING;

        try {
            $this->listeners->beforeStep($stepExecution);
            $this->dispatch(new BeforeStepEvent($stepExecution));

            $this->doExecute($stepExecution);

            // If doExecute didn't fail and didn't change status -> COMPLETED
            if (BatchStatus::STARTED === $stepExecution->getStatus()) {
                $stepExecution->setStatus(BatchStatus::COMPLETED);
            }
            $exitStatus = $stepExecution->getStatus()->isUnsuccessful()
                ? ExitStatus::$FAILED
                : ExitStatus::$COMPLETED;
        } catch (JobInterruptedException $e) {
            $stepExecution->addFailureException($e);
            $stepExecution->setStatus(BatchStatus::STOPPED);
            $exitStatus = ExitStatus::$STOPPED->addExitDescription($e->getMessage());
            $this->logger->info('Step interrupted: '.$e->getMessage(), ['step' => $this->name]);
        } catch (Throwable $e) {
            $stepExecution->addFailureException($e);
            $stepExecution->setStatus(BatchStatus::FAILED);
            $exitStatus = ExitStatus::$FAILED->addExitDescription($e->getMessage());
            $this->dispatch(new StepFailedEvent($stepExecution, $e));
            $this->logger->error('Step failed: '.$e->getMessage(), [
                'step' => $this->name,
                'exception' => $e,
            ]);
        } finally {
            $stepExecution->setExitStatus($exitStatus);
            $stepExecution->setEndTime(new DateTimeImmutable());

            try {
                $stepExecution->setExitStatus($this->listeners->afterStep($stepExecution));
            } catch (Throwable $listenerEx) {
                $this->logger->warning('afterStep listener failed: '.$listenerEx->getMessage());
                $stepExecution->addFailureException($listenerEx);
            }

            $this->dispatch(new AfterStepEvent($stepExecution));
            $this->jobRepository->updateExecutionContext($stepExecution);
            $this->jobRepository->update($stepExecution);
        }
    }

    public function getListeners(): CompositeListener
    {
        return $this->listeners;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStartLimit(): int
    {
        return $this->startLimit;
    }

    public function isAllowStartIfComplete(): bool
    {
        return $this->allowStartIfComplete;
    }

    public function registerListener(object $listener): void
    {
        $this->listeners->register($listener);
    }

    public function setAllowStartIfComplete(bool $value): void
    {
        $this->allowStartIfComplete = $value;
    }

    public function setEventDispatcher(?EventDispatcherInterface $dispatcher): void
    {
        $this->eventDispatcher = $dispatcher;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setStartLimit(int $limit): void
    {
        $this->startLimit = $limit;
    }

    protected function dispatch(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }

    abstract protected function doExecute(StepExecution $stepExecution): void;
}
