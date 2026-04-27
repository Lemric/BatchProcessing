Steps
=====

A **Step** is an independent, sequential phase of a batch **Job**. Each step
produces its own `StepExecution` with counters, status and an `ExecutionContext`.

StepInterface
-------------

```php
interface StepInterface
{
    public function getName(): string;
    public function execute(StepExecution $stepExecution): void;
    public function isAllowStartIfComplete(): bool;
    public function getStartLimit(): int;
}
```

Step Implementations
--------------------

| Class                | Purpose                                                  |
|----------------------|----------------------------------------------------------|
| `ChunkOrientedStep`  | Read-process-write step with retry/skip                  |
| `TaskletStep`        | Wraps a `TaskletInterface` for arbitrary unit-of-work    |
| `FlowStep`           | Wraps a `FlowInterface` so it can be executed as a step  |
| `JobStep`            | Wraps another Job and runs it as a step (composition)    |
| `PartitionStep`      | Runs a worker step against N partitions in parallel      |

ChunkOrientedStep
-----------------

Reads items in batches (`chunkSize`), processes each item, then writes the entire
chunk in a single transaction.

```php
use Lemric\BatchProcessing\BatchProcessing;

$env = BatchProcessing::inMemoryEnvironment();

$step = $env->stepBuilderFactory->get('importStep')
    ->chunk(
        chunkSize: 500,
        reader: $csvReader,
        processor: $orderProcessor,   // may be null
        writer: $dbWriter,
    )
    ->build();
```

See [Chunk-Oriented Processing](chunk-processing.md) for the full execution model.

TaskletStep
-----------

For logic that does not fit the read-process-write model:

```php
use Lemric\BatchProcessing\Step\{TaskletInterface, RepeatStatus};
use Lemric\BatchProcessing\Domain\StepContribution;
use Lemric\BatchProcessing\Chunk\ChunkContext;

$tasklet = new class implements TaskletInterface {
    public function execute(
        StepContribution $contribution,
        ChunkContext $chunkContext,
    ): RepeatStatus {
        // your logic here
        return RepeatStatus::FINISHED;
    }
};

$step = $env->stepBuilderFactory->get('cleanupStep')
    ->tasklet($tasklet)
    ->build();
```

Returning `RepeatStatus::CONTINUABLE` causes the framework to call `execute()`
again. See [Tasklet Steps](tasklets.md).

Building Steps (StepBuilder)
----------------------------

```php
$stepBuilderFactory = new StepBuilderFactory($repository, $transactionManager);

$step = $stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->faultTolerant()
    ->retry(\PDOException::class, maxAttempts: 3)
    ->backOff(new ExponentialBackOffPolicy(initial: 200, multiplier: 2.0, max: 5000))
    ->skip(\Lemric\BatchProcessing\Exception\SkippableException::class)
    ->skipLimit(100)
    ->listener($stepListener)
    ->build();
```

### Available Builder Methods

| Method                                              | Purpose                                              |
|-----------------------------------------------------|------------------------------------------------------|
| `chunk(int, ItemReader, ?ItemProcessor, ItemWriter)` | Configure as chunk-oriented step                    |
| `tasklet(TaskletInterface)`                          | Configure as tasklet step                           |
| `flow(FlowInterface)`                                | Configure as `FlowStep`                             |
| `job(JobInterface)`                                  | Configure as `JobStep` (run another job)            |
| `partitioner(PartitionerInterface)`                  | Configure as `PartitionStep` (data splitter)        |
| `workerStep(StepInterface)`                          | Worker step for partition mode                      |
| `gridSize(int)`                                      | Number of partitions                                 |
| `partitionHandler(StepHandlerInterface)`             | Custom partition handler (defaults to `TaskExecutorPartitionHandler`) |
| `faultTolerant()`                                    | Enable retry + skip pipelines                        |
| `retry(string $exceptionClass, int $maxAttempts = 3)`| Add a retry rule                                     |
| `noRetry(string $exceptionClass)`                    | Mark exception as non-retryable                      |
| `retryPolicy(RetryPolicyInterface)`                  | Use a fully-built retry policy                       |
| `backOff(BackOffPolicyInterface)`                    | Set the back-off policy                              |
| `skip(string $exceptionClass)`                       | Add a skip rule                                      |
| `noSkip(string $exceptionClass)`                     | Mark exception as non-skippable                      |
| `skipLimit(int)`                                     | Maximum number of skipped items                      |
| `skipPolicy(SkipPolicyInterface)`                    | Use a fully-built skip policy                        |
| `completionPolicy(CompletionPolicyInterface)`        | Override the default chunk completion policy         |
| `streams(array $streams)`                            | Register `ItemStreamInterface` streams explicitly    |
| `listener(object)`                                   | Add a listener                                       |
| `transactionManager(TransactionManagerInterface)`    | Override the transaction manager                     |
| `parametersExtractor(JobParametersExtractorInterface)` | Configure parameters extractor (used in JobStep)   |
| `jobLauncher(JobLauncherInterface)`                  | Custom launcher (used in JobStep)                    |
| `startLimit(int)`                                    | Maximum number of restart attempts                   |
| `allowStartIfComplete(bool $value = true)`           | Re-execute even if step is `COMPLETED`               |
| `build(): StepInterface`                             | Build the configured step                            |

### Static Shortcut

```php
use Lemric\BatchProcessing\BatchProcessing;

$step = BatchProcessing::step('myStep', $repository, $txManager)
    ->chunk(100, $reader, null, $writer)
    ->build();
```

Step Listeners
--------------

Attach listeners to receive callbacks before/after step execution and around
chunks:

```php
$step = $stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->listener($myStepListener)
    ->build();
```

See [Listeners & Events](events.md).

Next Steps
----------

* [Chunk-Oriented Processing](chunk-processing.md)
* [Tasklet Steps](tasklets.md)
* [Partition & Parallel Processing](partition.md)
* [Retry Framework](retry.md) · [Skip Framework](skip.md)

