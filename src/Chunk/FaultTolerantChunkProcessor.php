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

namespace Lemric\BatchProcessing\Chunk;

use Lemric\BatchProcessing\Domain\{StepContribution, StepExecution};
use Lemric\BatchProcessing\Item\{ItemProcessorInterface, ItemWriterInterface};
use Lemric\BatchProcessing\Item\Processor\PassThroughItemProcessor;
use Lemric\BatchProcessing\Listener\CompositeListener;
use Lemric\BatchProcessing\Retry\{RetryOperations, RetryTemplate};
use Lemric\BatchProcessing\Skip\{NeverSkipItemSkipPolicy, SkipPolicyInterface};
use Lemric\BatchProcessing\Transaction\{ResourcelessTransactionManager, TransactionManagerInterface};
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Skip/retry-aware {@see SimpleChunkProcessor}. On chunk-level write failure, falls back to a
 * per-item "scan" mode to identify the offending items and skip them according to the
 * configured {@see SkipPolicyInterface}.
 *
 * @template TIn
 * @template TOut
 *
 * @extends SimpleChunkProcessor<TIn, TOut>
 */
final class FaultTolerantChunkProcessor extends SimpleChunkProcessor
{
    /**
     * @param ItemProcessorInterface<TIn, TOut>|null $processor
     * @param ItemWriterInterface<TOut>              $writer
     * @param array<class-string<Throwable>, true>   $noRollbackExceptions
     */
    public function __construct(
        ?ItemProcessorInterface $processor,
        ItemWriterInterface $writer,
        TransactionManagerInterface $transactionManager = new ResourcelessTransactionManager(),
        CompositeListener $listeners = new CompositeListener(),
        ?LoggerInterface $logger = null,
        bool $processorNonTransactional = false,
        array $noRollbackExceptions = [],
        private readonly RetryOperations $retryOperations = new RetryTemplate(),
        private readonly SkipPolicyInterface $skipPolicy = new NeverSkipItemSkipPolicy(),
    ) {
        parent::__construct(
            $processor,
            $writer,
            $transactionManager,
            $listeners,
            $logger,
            $processorNonTransactional,
            $noRollbackExceptions,
        );
    }

    public function process(StepExecution $stepExecution, StepContribution $contribution, Chunk $chunk): void
    {
        if ($chunk->isEmpty() && !$chunk->isBusy()) {
            return;
        }
        $processor = $this->processor ?? new PassThroughItemProcessor();

        $outputs = $this->processorNonTransactional
            ? $this->runProcessorWithSkip($chunk, $processor, $stepExecution, $contribution)
            : [];

        $this->transactionManager->begin();
        try {
            if (!$this->processorNonTransactional) {
                $outputs = $this->runProcessorWithSkip($chunk, $processor, $stepExecution, $contribution);
            }

            if ([] !== $outputs) {
                /** @var Chunk<TIn, TOut> $writeChunk */
                $writeChunk = new Chunk([], $outputs);
                $this->listeners->beforeWrite($writeChunk);
                $this->retryOperations->execute(fn () => $this->writer->write($writeChunk));
                $this->listeners->afterWrite($writeChunk);
                $contribution->incrementWriteCount(count($outputs));
            }
            $stepExecution->incrementCommitCount();
            $this->transactionManager->commit();
        } catch (Throwable $e) {
            if ($this->isNoRollback($e)) {
                $this->transactionManager->commit();
                throw $e;
            }
            $this->transactionManager->rollback();
            $stepExecution->incrementRollbackCount();
            $this->listeners->onWriteError($e, $chunk);
            $this->scanForSkip($outputs, $stepExecution, $contribution, $e);
        }
    }

    /**
     * @param Chunk<TIn, TOut>                  $chunk
     * @param ItemProcessorInterface<TIn, TOut> $processor
     *
     * @return list<TOut>
     */
    private function runProcessorWithSkip(
        Chunk $chunk,
        ItemProcessorInterface $processor,
        StepExecution $stepExecution,
        StepContribution $contribution,
    ): array {
        /** @var list<TOut> $outputs */
        $outputs = [];
        foreach ($chunk->getInputItems() as $item) {
            try {
                $this->listeners->beforeProcess($item);
                $output = $this->retryOperations->execute(fn () => $processor->process($item));
                $this->listeners->afterProcess($item, $output);
                if (null === $output) {
                    $contribution->incrementFilterCount();
                    continue;
                }
                $outputs[] = $output;
            } catch (Throwable $e) {
                $this->listeners->onProcessError($item, $e);
                if ($this->skipPolicy->shouldSkip($e, $stepExecution->getProcessSkipCount())) {
                    $contribution->incrementProcessSkipCount();
                    $stepExecution->incrementProcessSkipCount();
                    $stepExecution->addSkippedItem($item);
                    $this->listeners->onSkipInProcess($item, $e);
                    continue;
                }
                throw $e;
            }
        }

        return $outputs;
    }

    /**
     * @param list<TOut> $outputs
     */
    private function scanForSkip(
        array $outputs,
        StepExecution $stepExecution,
        StepContribution $contribution,
        Throwable $original,
    ): void {
        if (!$this->skipPolicy->shouldSkip($original, $stepExecution->getWriteSkipCount())) {
            throw $original;
        }
        foreach ($outputs as $item) {
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
}
