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
use Lemric\BatchProcessing\Transaction\{ResourcelessTransactionManager, TransactionManagerInterface};
use Psr\Log\{LoggerInterface, NullLogger};
use Throwable;

/**
 * Reference {@see ChunkProcessorInterface}: processes every input item via the configured
 * {@see ItemProcessorInterface} (defaults to a pass-through), then writes the resulting
 * outputs in one transactional batch.
 *
 * Behavioural switches:
 *  - {@code processorNonTransactional}: when true, processor is invoked OUTSIDE the
 *    write transaction.
 *  - {@code noRollbackExceptions}: throwables of these classes do not trigger rollback —
 *    the transaction is committed and the failure is rethrown after commit.
 *
 * @template TIn
 * @template TOut
 *
 * @implements ChunkProcessorInterface<TIn, TOut>
 */
class SimpleChunkProcessor implements ChunkProcessorInterface
{
    protected LoggerInterface $logger;

    /**
     * @param ItemProcessorInterface<TIn, TOut>|null $processor
     * @param ItemWriterInterface<TOut>              $writer
     * @param array<class-string<Throwable>, true>   $noRollbackExceptions
     */
    public function __construct(
        protected readonly ?ItemProcessorInterface $processor,
        protected readonly ItemWriterInterface $writer,
        protected readonly TransactionManagerInterface $transactionManager = new ResourcelessTransactionManager(),
        protected readonly CompositeListener $listeners = new CompositeListener(),
        ?LoggerInterface $logger = null,
        protected readonly bool $processorNonTransactional = false,
        protected readonly array $noRollbackExceptions = [],
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function process(StepExecution $stepExecution, StepContribution $contribution, Chunk $chunk): void
    {
        if ($chunk->isEmpty() && !$chunk->isBusy()) {
            return;
        }

        $processor = $this->processor ?? new PassThroughItemProcessor();
        /** @var list<TOut> $outputs */
        $outputs = [];

        if ($this->processorNonTransactional) {
            $outputs = $this->runProcessor($chunk, $processor, $contribution);
        }

        $this->transactionManager->begin();
        try {
            if (!$this->processorNonTransactional) {
                $outputs = $this->runProcessor($chunk, $processor, $contribution);
            }

            if ([] !== $outputs) {
                /** @var Chunk<TIn, TOut> $writeChunk */
                $writeChunk = new Chunk([], $outputs);
                $this->listeners->beforeWrite($writeChunk);
                $this->writer->write($writeChunk);
                $this->listeners->afterWrite($writeChunk);
                $contribution->incrementWriteCount(count($outputs));
            }
            $stepExecution->incrementCommitCount();
            $this->transactionManager->commit();
        } catch (Throwable $e) {
            if ($this->isNoRollback($e)) {
                $this->logger->info('noRollback exception caught — committing chunk despite '.get_class($e));
                $this->transactionManager->commit();
                throw $e;
            }
            $this->transactionManager->rollback();
            $stepExecution->incrementRollbackCount();
            $this->listeners->onWriteError($e, $chunk);
            throw $e;
        }
    }

    protected function isNoRollback(Throwable $e): bool
    {
        foreach (array_keys($this->noRollbackExceptions) as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Chunk<TIn, TOut>                  $chunk
     * @param ItemProcessorInterface<TIn, TOut> $processor
     *
     * @return list<TOut>
     */
    protected function runProcessor(Chunk $chunk, ItemProcessorInterface $processor, StepContribution $contribution): array
    {
        /** @var list<TOut> $outputs */
        $outputs = [];
        foreach ($chunk->getInputItems() as $item) {
            $this->listeners->beforeProcess($item);
            $output = $processor->process($item);
            $this->listeners->afterProcess($item, $output);
            if (null === $output) {
                $contribution->incrementFilterCount();
                continue;
            }
            $outputs[] = $output;
        }

        return $outputs;
    }
}
