Class Index
===========

A reference index of public classes and interfaces, grouped by namespace.
Refer to the source files for the authoritative signatures.

`Lemric\BatchProcessing`
------------------------

| Class                         | Description                                            |
|-------------------------------|--------------------------------------------------------|
| `BatchProcessing`             | Static facade with `inMemory()`, `pdo()`, `async()`    |

`Lemric\BatchProcessing\Domain`
-------------------------------

| Class / Enum                       | Description                                       |
|------------------------------------|---------------------------------------------------|
| `BatchStatus` (enum)               | Execution state                                   |
| `ExitStatus`                       | Exit code value object (static instances)         |
| `JobInstance`                      | Logical job identity (name + key hash)            |
| `JobExecution`                     | Single execution attempt                          |
| `StepExecution`                    | Step execution with counters                      |
| `JobParameters`                    | Immutable parameter collection                    |
| `JobParameter`                     | Single typed parameter                            |
| `JobParametersBuilder`             | Fluent builder for `JobParameters`                |
| `ExecutionContext`                 | Persistent key-value map                          |
| `StepContribution`                 | Per-chunk metric accumulator                      |
| `DefaultJobParametersConverter`    | Convert between formats (key:value strings)       |

`Lemric\BatchProcessing\Job`
----------------------------

| Class / Interface                          | Description                                |
|--------------------------------------------|--------------------------------------------|
| `JobInterface`                             | Job contract                               |
| `AbstractJob`                              | Base implementation                        |
| `SimpleJob`                                | Sequential execution                       |
| `FlowJob`                                  | Conditional transitions                    |
| `JobBuilder`                               | Fluent job builder                         |
| `JobBuilderFactory`                        | Factory for `JobBuilder`                   |
| `FlowDeciderInterface`                     | Custom flow routing                        |
| `JobInterruptionPolicyInterface`           | Decide if a job should stop                |
| `SignalJobInterruptionPolicy`              | `pcntl` signal handling                    |
| `JobParametersIncrementerInterface`        | Auto-increment identifying parameters      |
| `RunIdIncrementer`, `DateIncrementer`, `CompositeIncrementer` | Built-in incrementers     |
| `JobParametersValidatorInterface`          | Validate parameters                        |
| `DefaultJobParametersValidator`            | Required/optional key validation           |
| `IdentifyingJobParametersValidator`        | Ensure specific keys are identifying       |
| `CompositeJobParametersValidator`          | Combine validators                         |
| `JobParametersExtractorInterface`          | Extract parameters (used by `JobStep`)     |
| `DefaultJobParametersExtractor`            | Default extractor                          |
| `Job\Flow\FlowInterface`                   | Flow contract                              |
| `Job\Flow\SimpleFlow`, `FlowBuilder`, `SimpleFlowFactory`, `Transition`, `FlowExecutionStatus` | Flow internals |
| `SplitFlow`                                | Parallel sub-flow execution                |

`Lemric\BatchProcessing\Step`
-----------------------------

| Class / Interface              | Description                                       |
|--------------------------------|---------------------------------------------------|
| `StepInterface`                | Step contract                                     |
| `AbstractStep`                 | Base with listener/event dispatch                 |
| `ChunkOrientedStep`            | Read-process-write step                           |
| `TaskletStep`                  | Wraps a `TaskletInterface`                        |
| `TaskletInterface`             | Arbitrary unit-of-work                            |
| `SystemCommandTasklet`         | Execute shell commands                            |
| `FlowStep`                     | Wraps a Flow as a step                            |
| `JobStep`                      | Wraps another Job as a step                       |
| `RepeatStatus` (enum)          | `FINISHED` / `CONTINUABLE`                        |
| `StepBuilder`, `StepBuilderFactory` | Fluent builder + factory                     |
| `Step\Builder\AbstractStepBuilder`, `FaultTolerantStepBuilder`, `FlowStepBuilder` | Internal builder helpers |

`Lemric\BatchProcessing\Item`
-----------------------------

Top-level interfaces:
`ItemReaderInterface`, `ItemProcessorInterface`, `ItemWriterInterface`,
`ItemStreamInterface`, `CompositeItemStream`,
`ResourceAwareItemReaderItemStreamInterface`,
`ResourceAwareItemWriterItemStreamInterface`.

### `Item\Reader`

`AbstractItemReader`, `IteratorItemReader`, `ListItemReader`,
`CallbackItemReader`, `PdoItemReader`, `PaginatedPdoItemReader`,
`PdoPagingItemReader`, `CsvItemReader` (+ `CsvFieldSetMapperInterface`),
`JsonItemReader`, `JsonLinesItemReader`, `MultiResourceItemReader`,
`TransformingItemReader`, `RedisItemReader` (+ `RedisDataStructure`),
`ScriptItemReader`, `SynchronizedItemStreamReader`, `ItemReaderAdapter`,
`Item\Reader\Paging\SqlPagingQueryProviderInterface`,
`Item\Reader\Paging\LimitOffsetPagingQueryProvider`.

### `Item\Processor`

`PassThroughItemProcessor`, `FilteringItemProcessor`, `CompositeItemProcessor`,
`ChainItemProcessor`, `ValidatingItemProcessor`, `BeanValidatingItemProcessor`,
`AsyncItemProcessor`, `ScriptItemProcessor`, `ItemProcessorAdapter`.

### `Item\Writer`

`AbstractItemWriter`, `PdoItemWriter`, `PdoBatchItemWriter`,
`CallbackItemWriter`, `CompositeItemWriter`, `ClassifierCompositeItemWriter`,
`FlatFileItemWriter`, `JsonFileItemWriter`, `RedisItemWriter`,
`MultiResourceItemWriter`, `AsyncItemWriter`, `ListItemWriter`,
`ItemWriterAdapter`.

### `Item\FlatFile`

`LineAggregatorInterface`, `LineMapperInterface`, `LineTokenizerInterface`,
`FieldExtractorInterface`, `FieldSetMapperInterface`, `FieldSetFactory`,
`DefaultFieldSet`, `FieldSet`, `DelimitedLineAggregator`,
`DelimitedLineTokenizer`, `FixedLengthTokenizer`, `FormatterLineAggregator`,
`PassThroughLineAggregator`, `PassThroughFieldExtractor`,
`PatternMatchingCompositeLineTokenizer`, `BeanWrapperFieldExtractor`,
`DefaultLineMapper`.

`Lemric\BatchProcessing\Chunk`
------------------------------

`Chunk`, `ChunkContext`, `ChunkProviderInterface`, `ChunkProcessorInterface`,
`SimpleChunkProvider`, `SimpleChunkProcessor`, `FaultTolerantChunkProvider`,
`FaultTolerantChunkProcessor`, `CompletionPolicyInterface`,
`SimpleCompletionPolicy`, `CountingCompletionPolicy`,
`TimeoutTerminationPolicy`, `CompositeCompletionPolicy`, `ChunkListener`.

`Lemric\BatchProcessing\Repository`
-----------------------------------

| Class / Interface                  | Description                              |
|------------------------------------|------------------------------------------|
| `JobRepositoryInterface`           | Persistence contract                     |
| `AbstractJobRepository`            | Common logic                             |
| `InMemoryJobRepository`            | In-memory implementation                 |
| `PdoJobRepository`                 | PDO implementation                       |
| `PdoJobRepositorySchema`           | DDL generator                            |
| `IsolationLevel` (enum)            | Transaction isolation                    |
| `BatchConfigurerInterface`         | Configuration contract                   |
| `DefaultBatchConfigurer`           | Default wiring                           |
| `Repository\Dao\*DaoInterface`     | Internal DAOs (used by `PdoJobRepository`)|
| `Repository\Dao\Pdo\*`             | PDO DAO implementations                  |
| `Repository\Incrementer\*`         | Sequence id incrementers (MySQL/Postgres/SQLite) |

`Lemric\BatchProcessing\Launcher`
---------------------------------

`JobLauncherInterface`, `AbstractJobLauncher`, `SimpleJobLauncher`,
`AsyncJobLauncher`, `TaskExecutorJobLauncher`, `SignalHandler`.

`Lemric\BatchProcessing\Explorer`
---------------------------------

`JobExplorerInterface`, `SimpleJobExplorer`, `AbstractCachedJobExplorer`,
`CachedJobExplorer`, `SimpleCacheJobExplorer`.

`Lemric\BatchProcessing\Operator`
---------------------------------

`JobOperatorInterface`, `SimpleJobOperator`.

`Lemric\BatchProcessing\Registry`
---------------------------------

`JobRegistryInterface`, `JobLocatorInterface`, `JobFactoryInterface`,
`InMemoryJobRegistry`, `ContainerJobRegistry`, `ContainerJobLocator`,
`ReferenceJobFactory`, `AttributeJobScanner`.

`Lemric\BatchProcessing\Retry`
------------------------------

| Class / Interface             | Description                                |
|-------------------------------|--------------------------------------------|
| `RetryOperations`             | Retry contract                             |
| `RetryTemplate`               | Default retry template                     |
| `RetryPolicyInterface`        | Policy contract                            |
| `RetryContext`, `RetryContextSupport` | Retry context + helpers            |
| `RetryCallback`               | Retry callback abstraction                 |
| `RecoveryCallbackInterface`   | Recovery contract                          |
| `RetrySynchronizationManager` | Cross-fiber/thread retry context           |
| `Retry\Interceptor\*`         | Retry interceptors (when applicable)       |

### `Retry\Policy`

`AbstractRetryPolicy`, `SimpleRetryPolicy`, `MaxAttemptsRetryPolicy`,
`NeverRetryPolicy`, `AlwaysRetryPolicy`, `TimeoutRetryPolicy`,
`CircuitBreakerRetryPolicy`, `ExceptionClassifierRetryPolicy`,
`BinaryExceptionClassifierRetryPolicy`, `CompositeRetryPolicy`.

### `Retry\Backoff`

`BackOffPolicyInterface`, `NoBackOffPolicy`, `FixedBackOffPolicy`,
`ExponentialBackOffPolicy`, `ExponentialRandomBackOffPolicy`,
`UniformRandomBackOffPolicy`.

`Lemric\BatchProcessing\Skip`
-----------------------------

`SkipPolicyInterface`, `LimitCheckingItemSkipPolicy`,
`AlwaysSkipItemSkipPolicy`, `NeverSkipItemSkipPolicy`,
`ExceptionClassifierSkipPolicy`, `ExceptionHierarchySkipPolicy`,
`CountingSkipPolicy`, `CompositeSkipPolicy`, `SkipCounter`.

`Lemric\BatchProcessing\Listener`
---------------------------------

Listener interfaces:
`JobExecutionListenerInterface`, `StepExecutionListenerInterface`,
`ChunkListenerInterface`, `ItemReadListenerInterface`,
`ItemProcessListenerInterface`, `ItemWriteListenerInterface`,
`SkipListenerInterface`, `RetryListenerInterface`.

Implementations & helpers:
`CompositeListener`, `StepListenerFactory`,
`ExecutionContextPromotionListener`, `ScopeResetListener`.

* `Listener\Logging\*` — PSR-3 logging listeners (`LoggingChunkListener`,
  `LoggingItemReadListener`, …).
* `Listener\Support\*` — NOOP base classes implementing each listener
  interface (`StepExecutionListenerSupport`, `ChunkListenerSupport`, …).

`Lemric\BatchProcessing\Event`
------------------------------

`AbstractJobEvent`, `BeforeJobEvent`, `AfterJobEvent`, `JobFailedEvent`,
`AbstractStepEvent`, `BeforeStepEvent`, `AfterStepEvent`, `StepFailedEvent`,
`AbstractChunkEvent`, `BeforeChunkEvent`, `AfterChunkEvent`,
`ChunkFailedEvent`.

`Lemric\BatchProcessing\Transaction`
------------------------------------

`TransactionManagerInterface`, `PdoTransactionManager`,
`ResourcelessTransactionManager`.

`Lemric\BatchProcessing\Partition`
----------------------------------

`PartitionerInterface`, `SimplePartitioner`, `ColumnRangePartitioner`,
`PartitionStep`, `StepHandlerInterface`, `TaskExecutorPartitionHandler`,
`StepExecutionSplitterInterface`, `SimpleStepExecutionSplitter`,
`StepExecutionAggregator`, `StepLocatorInterface`, `ContainerStepLocator`,
`PartitionNameProviderInterface`, `FiberTaskExecutor`, `ProcessTaskExecutor`.

`Lemric\BatchProcessing\Core`
-----------------------------

`TaskExecutorInterface`, `SyncTaskExecutor`, `SimpleAsyncTaskExecutor`.

`Lemric\BatchProcessing\Repeat`
-------------------------------

`RepeatOperationsInterface`, `RepeatTemplate`, `TaskExecutorRepeatTemplate`,
`RepeatContext`, `RepeatListenerInterface` (+ `Repeat\Executor\*`,
`Repeat\Support\*`).

`Lemric\BatchProcessing\Scope`
------------------------------

`AbstractScope`, `JobScope`, `StepScope`,
`Scope\Container\ScopedContainerInterface`,
`Scope\Container\InMemoryScopedContainer`,
`Scope\Expression\LateBindingExpressionResolverInterface`,
`Scope\Expression\SimpleLateBindingExpressionResolver`.

`Lemric\BatchProcessing\Classifier`
-----------------------------------

`ClassifierInterface`, `BinaryExceptionClassifier`, `SubclassClassifier`,
`BackToBackPatternClassifier`.

`Lemric\BatchProcessing\Attribute`
----------------------------------

`BatchJob`, `JobScope`, `StepScope`, plus per-callback markers in
`Attribute\Listener\*`: `BeforeJob`, `AfterJob`, `BeforeStep`, `AfterStep`,
`BeforeChunk`, `AfterChunk`, `BeforeRead`, `AfterRead`, `BeforeProcess`,
`AfterProcess`, `BeforeWrite`, `AfterWrite`, `OnReadError`, `OnWriteError`,
`OnSkipInRead`, `OnSkipInProcess`, `OnSkipInWrite`.

`Lemric\BatchProcessing\Testing`
--------------------------------

`JobLauncherTestUtils`, `JobRepositoryTestUtils`, `MetaDataInstanceFactory`,
`StepRunner`, `MockItemReader`, `InMemoryItemWriter`,
`ExecutionContextTestUtils`, `RetryContextMockFactory`,
`SkipContextMockFactory`, `JobScopeTestExecutionListener`,
`StepScopeTestExecutionListener`, `AssertFile`.

`Lemric\BatchProcessing\Bridge\Symfony`
---------------------------------------

`BatchProcessingBundle`, `DependencyInjection\BatchProcessingExtension`,
`DependencyInjection\Configuration`,
`DependencyInjection\Compiler\BatchJobPass`. Console commands in `Command/`
(launch/list/status/stop/restart/abandon/cleanup/health). Messenger
integration: `Messenger\MessengerJobDispatcher`, `Messenger\RunJobMessage`,
`Messenger\RunJobMessageHandler`. Additional integrations in the namespaces
`Item/`, `Lock/`, `Migration/`, `Profiler/` (e.g. `BatchDataCollector`),
`Scope/`, `Serializer/`, `Validator/`.

`Lemric\BatchProcessing\Bridge\Laravel`
---------------------------------------

`BatchProcessingServiceProvider`. Artisan commands in `Console/`
(launch/list/status/stop/restart/abandon/health). Additional integrations in
`Cache/`, `Item/` (`EloquentItemReader`, `EloquentItemWriter`), `Queue/`,
`Transaction/`, `Validator/`. Default `config/` and `database/` migrations
are published via `vendor:publish`.

`Lemric\BatchProcessing\Exception`
----------------------------------

See [Exception Hierarchy](exceptions.md) for the complete tree.

