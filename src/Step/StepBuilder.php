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

use Lemric\BatchProcessing\Chunk\CompletionPolicyInterface;
use Lemric\BatchProcessing\Item\{ItemProcessorInterface, ItemReaderInterface, ItemWriterInterface};
use Lemric\BatchProcessing\Job\Flow\FlowInterface;
use Lemric\BatchProcessing\Job\{JobInterface, JobParametersExtractorInterface};
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Lemric\BatchProcessing\Partition\{PartitionStep, PartitionerInterface, StepHandlerInterface, TaskExecutorPartitionHandler};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Retry\Backoff\{BackOffPolicyInterface, NoBackOffPolicy};
use Lemric\BatchProcessing\Retry\Policy\{NeverRetryPolicy, SimpleRetryPolicy};
use Lemric\BatchProcessing\Retry\{RetryOperations, RetryPolicyInterface, RetryTemplate};
use Lemric\BatchProcessing\Skip\{LimitCheckingItemSkipPolicy, NeverSkipItemSkipPolicy, SkipPolicyInterface};
use Lemric\BatchProcessing\Transaction\{ResourcelessTransactionManager, TransactionManagerInterface};
use LogicException;
use Throwable;

use const PHP_INT_MAX;

/**
 * Fluent builder for {@see ChunkOrientedStep}, {@see TaskletStep}, {@see PartitionStep},
 * {@see FlowStep} and {@see JobStep}.
 */
final class StepBuilder
{
    private bool $allowStartIfComplete = false;

    private ?BackOffPolicyInterface $backOffPolicy = null;

    private ?JobInterface $childJob = null;

    private int $chunkSize = 1;

    private ?CompletionPolicyInterface $completionPolicy = null;

    private bool $faultTolerant = false;

    private ?FlowInterface $flow = null;

    private ?JobLauncherInterface $jobLauncher = null;

    /** @var list<object> */
    private array $listeners = [];

    /** @var array<class-string<Throwable>, bool> */
    private array $noSkipExceptions = [];

    private ?JobParametersExtractorInterface $parametersExtractor = null;

    private ?PartitionerInterface $partitioner = null;

    private int $partitionGridSize = 6;

    private ?StepHandlerInterface $partitionHandler = null;

    /** @var ItemProcessorInterface<mixed, mixed>|null */
    private ?ItemProcessorInterface $processor = null;

    /** @var ItemReaderInterface<mixed>|null */
    private ?ItemReaderInterface $reader = null;

    /** @var array<class-string<Throwable>, bool> */
    private array $retryableExceptions = [];

    private int $retryAttempts = 1;

    private ?RetryPolicyInterface $retryPolicy = null;

    private int $skipLimit = 0;

    /** @var array<class-string<Throwable>, bool> */
    private array $skippableExceptions = [];

    private ?SkipPolicyInterface $skipPolicy = null;

    private int $startLimit = PHP_INT_MAX;

    /** @var list<\Lemric\BatchProcessing\Item\ItemStreamInterface> */
    private array $streams = [];

    private ?TaskletInterface $tasklet = null;

    private TransactionManagerInterface $transactionManager;

    private ?StepInterface $workerStep = null;

    /** @var ItemWriterInterface<mixed>|null */
    private ?ItemWriterInterface $writer = null;

    public function __construct(
        private readonly string $name,
        private readonly JobRepositoryInterface $jobRepository,
        ?TransactionManagerInterface $transactionManager = null,
    ) {
        $this->transactionManager = $transactionManager ?? new ResourcelessTransactionManager();
    }

    public function allowStartIfComplete(bool $value = true): self
    {
        $this->allowStartIfComplete = $value;

        return $this;
    }

    public function backOff(BackOffPolicyInterface $policy): self
    {
        $this->backOffPolicy = $policy;

        return $this;
    }

    public function build(): StepInterface
    {
        if (null !== $this->flow) {
            $step = new FlowStep(
                $this->name,
                $this->jobRepository,
                $this->flow,
            );
        } elseif (null !== $this->childJob) {
            if (null === $this->jobLauncher) {
                throw new LogicException("JobStep '{$this->name}' requires a jobLauncher (call jobLauncher()).");
            }
            $step = new JobStep(
                $this->name,
                $this->jobRepository,
                $this->childJob,
                $this->jobLauncher,
                $this->parametersExtractor,
            );
        } elseif (null !== $this->partitioner) {
            if (null === $this->workerStep) {
                throw new LogicException("Partitioned step '{$this->name}' requires a worker step (call workerStep()).");
            }
            $step = new PartitionStep(
                $this->name,
                $this->jobRepository,
                $this->partitioner,
                $this->workerStep,
                $this->partitionHandler ?? new TaskExecutorPartitionHandler(),
            );
            $step->setGridSize($this->partitionGridSize);
        } elseif (null !== $this->tasklet) {
            $step = new TaskletStep(
                $this->name,
                $this->jobRepository,
                $this->tasklet,
                $this->transactionManager,
            );
        } else {
            if (null === $this->reader || null === $this->writer) {
                throw new LogicException("Step '{$this->name}' requires either a tasklet, partitioner, flow, job or a chunk(reader, ?processor, writer) configuration.");
            }
            $step = new ChunkOrientedStep(
                name: $this->name,
                jobRepository: $this->jobRepository,
                reader: $this->reader,
                processor: $this->processor,
                writer: $this->writer,
                chunkSize: $this->chunkSize,
                transactionManager: $this->transactionManager,
                retryOperations: $this->buildRetryOperations(),
                skipPolicy: $this->buildSkipPolicy(),
                completionPolicy: $this->completionPolicy,
            );
        }

        foreach ($this->listeners as $listener) {
            $step->registerListener($listener);
        }

        $step->setAllowStartIfComplete($this->allowStartIfComplete);
        $step->setStartLimit($this->startLimit);

        if ($step instanceof ChunkOrientedStep) {
            foreach ($this->streams as $stream) {
                $step->registerStream($stream);
            }
        }

        return $step;
    }

    /**
     * @template TIn
     * @template TOut
     *
     * @param ItemReaderInterface<TIn>               $reader
     * @param ItemProcessorInterface<TIn, TOut>|null $processor
     * @param ItemWriterInterface<TOut>              $writer
     */
    public function chunk(
        int $chunkSize,
        ItemReaderInterface $reader,
        ?ItemProcessorInterface $processor,
        ItemWriterInterface $writer,
    ): self {
        if (null !== $this->tasklet) {
            throw new LogicException('Step is already configured as tasklet.');
        }
        $this->chunkSize = $chunkSize;
        $this->reader = $reader;
        $this->processor = $processor;
        $this->writer = $writer;

        return $this;
    }

    /**
     * Use a {@see CompletionPolicyInterface} instead of a hard-coded chunkSize for chunk completion.
     */
    public function completionPolicy(CompletionPolicyInterface $policy): self
    {
        $this->completionPolicy = $policy;

        return $this;
    }

    public function faultTolerant(): self
    {
        $this->faultTolerant = true;

        return $this;
    }

    /**
     * Configure this step as a {@see FlowStep} wrapping the given flow.
     */
    public function flow(FlowInterface $flow): self
    {
        $this->flow = $flow;

        return $this;
    }

    /**
     * Number of partitions requested from the {@see PartitionerInterface}.
     */
    public function gridSize(int $gridSize): self
    {
        $this->partitionGridSize = max(1, $gridSize);

        return $this;
    }

    /**
     * Configure this step as a {@see JobStep} running a child job.
     */
    public function job(JobInterface $job): self
    {
        $this->childJob = $job;

        return $this;
    }

    /**
     * Set the job launcher for a {@see JobStep}.
     */
    public function jobLauncher(JobLauncherInterface $launcher): self
    {
        $this->jobLauncher = $launcher;

        return $this;
    }

    public function listener(object $listener): self
    {
        $this->listeners[] = $listener;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function noRetry(string $exceptionClass): self
    {
        $this->retryableExceptions[$exceptionClass] = false;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function noSkip(string $exceptionClass): self
    {
        $this->noSkipExceptions[$exceptionClass] = true;

        return $this;
    }

    /**
     * Set the parameters extractor for a {@see JobStep}.
     */
    public function parametersExtractor(JobParametersExtractorInterface $extractor): self
    {
        $this->parametersExtractor = $extractor;

        return $this;
    }

    /**
     * Switch the builder into partitioned mode: the produced {@see PartitionStep} will split
     * work using the supplied {@see PartitionerInterface} and execute the worker step (set via
     * {@see workerStep()}) for every partition.
     */
    public function partitioner(PartitionerInterface $partitioner): self
    {
        if (null !== $this->reader || null !== $this->tasklet) {
            throw new LogicException('Step is already configured as chunk/tasklet.');
        }
        $this->partitioner = $partitioner;

        return $this;
    }

    /**
     * Optional partition execution strategy (defaults to a sequential
     * {@see TaskExecutorPartitionHandler}).
     */
    public function partitionHandler(StepHandlerInterface $handler): self
    {
        $this->partitionHandler = $handler;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function retry(string $exceptionClass, int $maxAttempts = 3): self
    {
        $this->faultTolerant = true;
        $this->retryableExceptions[$exceptionClass] = true;
        $this->retryAttempts = max($this->retryAttempts, $maxAttempts);

        return $this;
    }

    /**
     * Use a fully constructed {@see RetryPolicyInterface} instead of the implicit
     * {@see SimpleRetryPolicy} built from {@see retry()} calls. Required when combining
     * timeout-based, classifier-based or composite policies.
     */
    public function retryPolicy(RetryPolicyInterface $policy): self
    {
        $this->retryPolicy = $policy;
        $this->faultTolerant = true;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function skip(string $exceptionClass): self
    {
        $this->faultTolerant = true;
        $this->skippableExceptions[$exceptionClass] = true;

        return $this;
    }

    public function skipLimit(int $limit): self
    {
        $this->skipLimit = $limit;

        return $this;
    }

    /**
     * Use a fully constructed {@see SkipPolicyInterface} instead of the implicit
     * {@see LimitCheckingItemSkipPolicy} built from {@see skip()} calls. Required for
     * exception-classifier or always-skip strategies.
     */
    public function skipPolicy(SkipPolicyInterface $policy): self
    {
        $this->skipPolicy = $policy;
        $this->faultTolerant = true;

        return $this;
    }

    public function startLimit(int $limit): self
    {
        $this->startLimit = $limit;

        return $this;
    }

    /**
     * @param list<\Lemric\BatchProcessing\Item\ItemStreamInterface> $streams
     */
    public function streams(array $streams): self
    {
        $this->streams = $streams;

        return $this;
    }

    public function tasklet(TaskletInterface $tasklet): self
    {
        if (null !== $this->reader) {
            throw new LogicException('Step is already configured as chunk-oriented.');
        }
        $this->tasklet = $tasklet;

        return $this;
    }

    public function transactionManager(TransactionManagerInterface $manager): self
    {
        $this->transactionManager = $manager;

        return $this;
    }

    /**
     * Worker step executed for each partition. Must be a fully built {@see StepInterface}
     * (typically a chunk-oriented step) — pair with {@see partitioner()}.
     */
    public function workerStep(StepInterface $worker): self
    {
        $this->workerStep = $worker;

        return $this;
    }

    private function buildRetryOperations(): RetryOperations
    {
        if (null !== $this->retryPolicy) {
            return new RetryTemplate($this->retryPolicy, $this->backOffPolicy ?? new NoBackOffPolicy());
        }

        if (!$this->faultTolerant || [] === $this->retryableExceptions) {
            return new RetryTemplate(new NeverRetryPolicy(), new NoBackOffPolicy());
        }

        return new RetryTemplate(
            new SimpleRetryPolicy($this->retryAttempts, $this->retryableExceptions),
            $this->backOffPolicy ?? new NoBackOffPolicy(),
        );
    }

    private function buildSkipPolicy(): SkipPolicyInterface
    {
        if (null !== $this->skipPolicy) {
            return $this->skipPolicy;
        }

        if (!$this->faultTolerant || [] === $this->skippableExceptions) {
            return new NeverSkipItemSkipPolicy();
        }

        if ($this->skipLimit <= 0) {
            throw new LogicException("Step '{$this->name}': skipLimit must be > 0 when skippable exceptions are configured (call skipLimit()).");
        }

        $merged = $this->skippableExceptions;
        foreach (array_keys($this->noSkipExceptions) as $class) {
            $merged[$class] = false;
        }

        return new LimitCheckingItemSkipPolicy(
            $this->skipLimit,
            $merged,
        );
    }
}
