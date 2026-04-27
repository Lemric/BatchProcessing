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

use InvalidArgumentException;
use Lemric\BatchProcessing\Chunk\{Chunk, ChunkContext, CompletionPolicyInterface};
use Lemric\BatchProcessing\Domain\{BatchStatus, StepContribution, StepExecution};
use Lemric\BatchProcessing\Event\{AfterChunkEvent, BeforeChunkEvent, ChunkFailedEvent};
use Lemric\BatchProcessing\Exception\StepExecutionException;
use Lemric\BatchProcessing\Item\{CompositeItemStream, ItemProcessorInterface, ItemReaderInterface, ItemStreamInterface, ItemWriterInterface};
use Lemric\BatchProcessing\Item\Processor\PassThroughItemProcessor;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Retry\{RetryOperations, RetryTemplate};
use Lemric\BatchProcessing\Skip\{NeverSkipItemSkipPolicy, SkipPolicyInterface};
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;
use Throwable;

/**
 * Heart of the framework: chunk-oriented step.
 *
 * Algorithm:
 *  - open() all streams (with the persisted ExecutionContext for restart)
 *  - loop:
 *     - read N items into a chunk (with retry / skip on read())
 *     - process each item (with retry / skip on process())
 *     - begin transaction → write chunk → commit
 *     - on write failure: rollback, then "scan mode": retry per-item to identify the offender
 *     - update streams (checkpoint into ExecutionContext)
 *     - persist StepExecution and ExecutionContext via the JobRepository
 *  - close() all streams (in finally)
 *
 * @template TIn
 * @template TOut
 */
final class ChunkOrientedStep extends AbstractStep
{
    /** Defends against stack overflow when read errors repeat and skipping is enabled. */
    private const int MAX_CONSECUTIVE_READ_SKIPS = 4096;

    private CompositeItemStream $streamManager;

    /**
     * @param ItemReaderInterface<TIn>               $reader
     * @param ItemProcessorInterface<TIn, TOut>|null $processor null = pass-through
     * @param ItemWriterInterface<TOut>              $writer
     */
    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        private readonly ItemReaderInterface $reader,
        private readonly ?ItemProcessorInterface $processor,
        private readonly ItemWriterInterface $writer,
        private readonly int $chunkSize,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly RetryOperations $retryOperations = new RetryTemplate(),
        private readonly SkipPolicyInterface $skipPolicy = new NeverSkipItemSkipPolicy(),
        private readonly ?CompletionPolicyInterface $completionPolicy = null,
    ) {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('chunkSize must be >= 1');
        }
        parent::__construct($name, $jobRepository);
        $this->streamManager = new CompositeItemStream();
        if ($reader instanceof ItemStreamInterface) {
            $this->streamManager->register($reader);
        }
        if ($processor instanceof ItemStreamInterface) {
            $this->streamManager->register($processor);
        }
        if ($writer instanceof ItemStreamInterface) {
            $this->streamManager->register($writer);
        }
    }

    /**
     * Allow user code to register additional streams (e.g. wrapped resources).
     */
    public function registerStream(ItemStreamInterface $stream): void
    {
        $this->streamManager->register($stream);
    }

    protected function doExecute(StepExecution $stepExecution): void
    {
        $ctx = $stepExecution->getExecutionContext();
        $this->streamManager->open($ctx);

        try {
            while (true) {
                if ($stepExecution->isTerminateOnly() || $stepExecution->getJobExecution()->isStopping()) {
                    $stepExecution->setStatus(BatchStatus::STOPPED);
                    break;
                }

                $contribution = new StepContribution($stepExecution);
                $chunkContext = new ChunkContext($contribution);
                $this->listeners->beforeChunk($chunkContext);
                $this->dispatch(new BeforeChunkEvent($chunkContext));

                $chunk = $this->readAndProcessChunk($stepExecution, $contribution);

                if ($chunk->isEmpty() && !$chunk->isBusy()) {
                    // No items read at all → end of data. No transaction needed.
                    break;
                }

                try {
                    $this->writeChunk($chunk, $stepExecution, $contribution);
                } catch (Throwable $e) {
                    $this->listeners->afterChunkError($chunkContext, $e);
                    $this->dispatch(new ChunkFailedEvent($chunkContext, $e));
                    throw $e;
                }

                $contribution->apply();
                $chunkContext->setComplete();

                // Checkpoint: persist stream state and metadata
                $this->streamManager->update($ctx);
                $this->jobRepository->updateExecutionContext($stepExecution);
                $this->jobRepository->update($stepExecution);

                $this->listeners->afterChunk($chunkContext);
                $this->dispatch(new AfterChunkEvent($chunkContext));

                if ($chunk->getInputCount() < $this->chunkSize) {
                    // Reader returned fewer items than requested → reached EoD this iteration.
                    break;
                }
            }
        } finally {
            try {
                $this->streamManager->close();
            } catch (Throwable $closeException) {
                $stepExecution->addFailureException($closeException);
                $this->logger->warning('Stream close failed: '.$closeException->getMessage());
            }
        }
    }

    /**
     * @param TIn                               $item
     * @param ItemProcessorInterface<TIn, TOut> $processor
     *
     * @return TOut|null
     */
    private function doProcess(
        mixed $item,
        StepExecution $stepExecution,
        StepContribution $contribution,
        ItemProcessorInterface $processor,
    ): mixed {
        try {
            $this->listeners->beforeProcess($item);
            $result = $this->retryOperations->execute(
                fn () => $processor->process($item),
            );
            $this->listeners->afterProcess($item, $result);

            return $result;
        } catch (Throwable $e) {
            $this->listeners->onProcessError($item, $e);
            if ($this->skipPolicy->shouldSkip($e, $stepExecution->getProcessSkipCount())) {
                $contribution->incrementProcessSkipCount();
                $stepExecution->incrementProcessSkipCount();
                $stepExecution->addSkippedItem($item);
                $this->listeners->onSkipInProcess($item, $e);
                $this->logger->warning('Skipping un-processable item: '.$e->getMessage());

                return null;
            }
            throw $e;
        }
    }

    /**
     * @return TIn|null
     */
    private function doRead(StepExecution $stepExecution, StepContribution $contribution): mixed
    {
        $consecutiveReadSkips = 0;
        while (true) {
            try {
                $this->listeners->beforeRead();
                $item = $this->retryOperations->execute(
                    fn () => $this->reader->read(),
                );
                if (null !== $item) {
                    $this->listeners->afterRead($item);
                }

                return $item;
            } catch (Throwable $e) {
                $this->listeners->onReadError($e);
                if ($this->skipPolicy->shouldSkip($e, $stepExecution->getReadSkipCount())) {
                    ++$consecutiveReadSkips;
                    if ($consecutiveReadSkips > self::MAX_CONSECUTIVE_READ_SKIPS) {
                        throw new StepExecutionException(sprintf('Exceeded maximum of %d consecutive read skips (possible infinite skip loop).', self::MAX_CONSECUTIVE_READ_SKIPS), previous: $e);
                    }
                    $contribution->incrementReadSkipCount();
                    $stepExecution->incrementReadSkipCount();
                    $this->listeners->onSkipInRead($e);
                    $this->logger->warning('Skipping unreadable item: '.$e->getMessage());

                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * @return Chunk<TIn, TOut>
     */
    private function readAndProcessChunk(StepExecution $stepExecution, StepContribution $contribution): Chunk
    {
        /** @var list<TIn> $inputs */
        $inputs = [];
        /** @var list<TOut> $outputs */
        $outputs = [];

        $processor = $this->processor ?? new PassThroughItemProcessor();

        // Use CompletionPolicy if provided, otherwise fall back to chunkSize counter.
        $chunkContext = new ChunkContext($contribution);
        $policy = $this->completionPolicy;
        if (null !== $policy) {
            $policy->start($chunkContext);
        }

        $i = 0;
        while (true) {
            // Check termination: either CompletionPolicy says done, or we hit chunkSize.
            if (null !== $policy) {
                if ($policy->isComplete($chunkContext)) {
                    break;
                }
            } elseif ($i >= $this->chunkSize) {
                break;
            }

            $item = $this->doRead($stepExecution, $contribution);
            if (null === $item) {
                break;
            }

            $contribution->incrementReadCount();
            $inputs[] = $item;
            ++$i;

            if (null !== $policy) {
                $policy->update($chunkContext);
            }

            $output = $this->doProcess($item, $stepExecution, $contribution, $processor);
            if (null === $output) {
                $contribution->incrementFilterCount();
                continue;
            }
            $outputs[] = $output;
        }

        return new Chunk($inputs, $outputs);
    }

    /**
     * @param Chunk<TIn, TOut> $chunk
     */
    private function scanForSkip(
        Chunk $chunk,
        StepExecution $stepExecution,
        StepContribution $contribution,
        Throwable $original,
    ): void {
        // First check whether the original exception is even skippable. If not, propagate
        // immediately - re-running each item might trigger irrecoverable side effects.
        if (!$this->skipPolicy->shouldSkip($original, $stepExecution->getWriteSkipCount())) {
            throw $original;
        }

        foreach ($chunk->getOutputItems() as $item) {
            /** @var Chunk<TIn, TOut> $singleChunk */
            $singleChunk = new Chunk([], [$item]);
            $this->transactionManager->begin();
            try {
                $this->writer->write($singleChunk);
                $contribution->incrementWriteCount(1);
                $stepExecution->incrementCommitCount();
                $this->transactionManager->commit();
            } catch (Throwable $itemEx) {
                $this->transactionManager->rollback();
                $stepExecution->incrementRollbackCount();
                if ($this->skipPolicy->shouldSkip($itemEx, $stepExecution->getWriteSkipCount())) {
                    $contribution->incrementWriteSkipCount();
                    $stepExecution->incrementWriteSkipCount();
                    $stepExecution->addSkippedItem($item);
                    $this->listeners->onSkipInWrite($item, $itemEx);
                    $this->logger->warning('Skipping un-writeable item: '.$itemEx->getMessage());
                } else {
                    throw $itemEx;
                }
            }
        }
    }

    /**
     * @param Chunk<TIn, TOut> $chunk
     */
    private function writeChunk(Chunk $chunk, StepExecution $stepExecution, StepContribution $contribution): void
    {
        if (0 === $chunk->getOutputCount()) {
            // Nothing to write but we still want a clean transactional boundary so that
            // commit/rollback counters stay accurate. Treat empty chunks as no-ops.
            $stepExecution->incrementCommitCount();

            return;
        }

        $this->transactionManager->begin();
        try {
            $this->listeners->beforeWrite($chunk);
            $this->writer->write($chunk);
            $this->listeners->afterWrite($chunk);

            $contribution->incrementWriteCount($chunk->getOutputCount());
            $stepExecution->incrementCommitCount();
            $this->transactionManager->commit();
        } catch (Throwable $e) {
            $this->transactionManager->rollback();
            $stepExecution->incrementRollbackCount();
            $this->listeners->onWriteError($e, $chunk);
            $this->scanForSkip($chunk, $stepExecution, $contribution, $e);
        }
    }
}
