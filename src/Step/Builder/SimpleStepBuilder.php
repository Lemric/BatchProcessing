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

namespace Lemric\BatchProcessing\Step\Builder;

use Lemric\BatchProcessing\Chunk\CompletionPolicyInterface;
use Lemric\BatchProcessing\Item\{ItemProcessorInterface, ItemReaderInterface, ItemStreamInterface, ItemWriterInterface};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\{ChunkOrientedStep, StepInterface};
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;
use LogicException;
use Throwable;

/**
 * {@code SimpleStepBuilder} parity. Builds a non-fault-tolerant
 * {@see ChunkOrientedStep}. For retry/skip configuration use {@see FaultTolerantStepBuilder}.
 *
 * @template TIn
 * @template TOut
 *
 * @extends AbstractStepBuilder<ChunkOrientedStep<TIn, TOut>>
 */
class SimpleStepBuilder extends AbstractStepBuilder
{
    protected int $chunkSize = 1;

    protected ?CompletionPolicyInterface $completionPolicy = null;

    /** @var array<class-string<Throwable>, true> */
    protected array $noRollbackExceptions = [];

    /** @var ItemProcessorInterface<TIn, TOut>|null */
    protected ?ItemProcessorInterface $processor = null;

    protected bool $processorNonTransactional = false;

    /** @var ItemReaderInterface<TIn>|null */
    protected ?ItemReaderInterface $reader = null;

    protected bool $readerTransactionalQueue = false;

    /** @var list<ItemStreamInterface> */
    protected array $streams = [];

    /** @var ItemWriterInterface<TOut>|null */
    protected ?ItemWriterInterface $writer = null;

    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        ?TransactionManagerInterface $transactionManager = null,
    ) {
        parent::__construct($name, $jobRepository, $transactionManager);
    }

    public function build(): StepInterface
    {
        if (null === $this->reader || null === $this->writer) {
            throw new LogicException("SimpleStepBuilder for '{$this->name}' requires both reader() and writer().");
        }

        $step = new ChunkOrientedStep(
            name: $this->name,
            jobRepository: $this->jobRepository,
            reader: $this->reader,
            processor: $this->processor,
            writer: $this->writer,
            chunkSize: $this->chunkSize,
            transactionManager: $this->transactionManager,
            completionPolicy: $this->completionPolicy,
        );

        foreach ($this->streams as $stream) {
            $step->registerStream($stream);
        }

        $this->applyCommon($step);

        // NOTE: noRollbackExceptions / processorNonTransactional / readerTransactionalQueue
        // become effective when the step is wired through the new ChunkProvider/Processor
        // pipeline. They are exposed as getters below for consumers wiring the pipeline
        // manually (see Lemric\BatchProcessing\Chunk\SimpleChunkProcessor).

        return $step;
    }

    public function chunk(int $chunkSize): static
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    public function completionPolicy(CompletionPolicyInterface $policy): static
    {
        $this->completionPolicy = $policy;

        return $this;
    }

    /**
     * @return array<class-string<Throwable>, true>
     */
    public function getNoRollbackExceptions(): array
    {
        return $this->noRollbackExceptions;
    }

    public function isProcessorNonTransactional(): bool
    {
        return $this->processorNonTransactional;
    }

    public function isReaderTransactionalQueue(): bool
    {
        return $this->readerTransactionalQueue;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function noRollback(string $exceptionClass): static
    {
        $this->noRollbackExceptions[$exceptionClass] = true;

        return $this;
    }

    /**
     * @param ItemProcessorInterface<TIn, TOut> $processor
     */
    public function processor(ItemProcessorInterface $processor): static
    {
        $this->processor = $processor;

        return $this;
    }

    public function processorNonTransactional(bool $value = true): static
    {
        $this->processorNonTransactional = $value;

        return $this;
    }

    /**
     * @param ItemReaderInterface<TIn> $reader
     */
    public function reader(ItemReaderInterface $reader): static
    {
        $this->reader = $reader;

        return $this;
    }

    public function readerTransactionalQueue(bool $value = true): static
    {
        $this->readerTransactionalQueue = $value;

        return $this;
    }

    public function stream(ItemStreamInterface $stream): static
    {
        $this->streams[] = $stream;

        return $this;
    }

    /**
     * @param list<ItemStreamInterface> $streams
     */
    public function streams(array $streams): static
    {
        $this->streams = $streams;

        return $this;
    }

    /**
     * @param ItemWriterInterface<TOut> $writer
     */
    public function writer(ItemWriterInterface $writer): static
    {
        $this->writer = $writer;

        return $this;
    }
}
