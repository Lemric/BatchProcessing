# Changelog

All notable changes to **Lemric BatchProcessing** will be documented in this
file.

The format is based on [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Static facade environment API now returns a typed `BatchEnvironment` object:
  - `BatchProcessing::inMemoryEnvironment()`
  - `BatchProcessing::pdoEnvironment(\PDO $pdo, string $tablePrefix = 'batch_')`
  - `BatchProcessing::asyncEnvironment(callable $dispatcher, ?JobRepositoryInterface, ?TransactionManagerInterface)`
- Added `BatchProcessing::environment(?callable $configure = null)` and
  `BatchProcessing::builder()` as canonical programmatic entry points.
- Added `BatchEnvironment::toBuilder()` to support safe reconfiguration from an
  existing environment.
- `BatchEnvironmentBuilder` now supports factory-based customization for every
  dependency (`with*Factory()` methods), while keeping fluent direct overrides.

## [1.0.0] – 2026-04-27

Initial public release. Lemric BatchProcessing is an enterprise-grade,
framework-agnostic batch processing library for PHP 8.4+, aligned with PHP-FIG standards (PSR-3, PSR-6, PSR-11,
PSR-14, PSR-16).

### Added

#### Static Facade

- `Lemric\BatchProcessing\BatchProcessing` — single entry point with three
  bootstrap helpers:
  - `BatchProcessing::inMemory()` for tests and scripts.
  - `BatchProcessing::pdo(\PDO $pdo, string $tablePrefix = 'batch_')` for
    production deployments.
  - `BatchProcessing::async(callable $dispatcher, ?JobRepositoryInterface,
    ?TransactionManagerInterface)` for queue-/bus-based execution.
  - `BatchProcessing::job(string $name, JobRepositoryInterface)` and
    `BatchProcessing::step(string $name, JobRepositoryInterface,
    ?TransactionManagerInterface)` shortcuts.

#### Domain Model (`Lemric\BatchProcessing\Domain`)

- `BatchStatus` backed enum (`STARTING`, `STARTED`, `STOPPING`, `STOPPED`,
  `FAILED`, `COMPLETED`, `ABANDONED`) with `isRunning()`,
  `isUnsuccessful()`, `isGreaterThan()`, `ordinal()` and `upgradeTo()`.
- `ExitStatus` value object with static instances (`$UNKNOWN`, `$EXECUTING`,
  `$COMPLETED`, `$NOOP`, `$FAILED`, `$STOPPED`) and the matching `*_CODE`
  constants. Supports `and()`, `replaceExitCode()`, `addExitDescription()`,
  `compareTo()` and `isRunning()`.
- `JobInstance`, `JobExecution`, `StepExecution` execution records with
  full counter API (`readCount`, `writeCount`, `filterCount`,
  `commitCount`, `rollbackCount`, `readSkipCount`, `processSkipCount`,
  `writeSkipCount`).
- `JobParameters` immutable value object with `getString()`, `getLong()`,
  `getDouble()`, `getDate()`, `getIdentifyingParameters()`,
  `identifyingOnly()`, `toIdentifyingString()`, `toJobKey()` plus the
  `JobParameters::of(array)` factory.
- `JobParameter` typed parameter with `ofString()`, `ofLong()`,
  `ofDouble()`, `ofDate()` factories and `TYPE_*` constants.
- `JobParametersBuilder` fluent builder.
- `ExecutionContext` persistent key-value map with typed accessors
  (`getInt`, `getString`, `getFloat`, `getDouble`, `getBool`),
  `containsKey`, `containsValue`, `merge`, `putIfAbsent`, dirty-flag
  tracking and reserved counter keys (`READ_COUNT`, `WRITE_COUNT`,
  `FILTER_COUNT`, `READ_SKIP`, `WRITE_SKIP`, `PROCESS_SKIP`).
- `StepContribution` per-chunk metric accumulator.
- `DefaultJobParametersConverter` for `key:value` (de)serialisation used by
  console commands.

#### Job Layer (`Lemric\BatchProcessing\Job`)

- `JobInterface` with `execute()`, `getName()`, `isRestartable()` and
  `validateParameters()`.
- `AbstractJob` template-method base class with PSR-3 logger and PSR-14
  event dispatcher hooks.
- `SimpleJob` for sequential step execution.
- `FlowJob` with conditional transitions, deciders and split flows.
- `JobBuilder` / `JobBuilderFactory` with fluent API: `start()`, `next()`,
  `flow()`, `transition()`, `decider()`, `split()`, `listener()`,
  `incrementer()`, `withRunIdIncrementer()`, `validator()`,
  `interruptionPolicy()`, `preventRestart()`, `allowStartIfComplete()`,
  `eventDispatcher()`, `logger()`.
- Parameter incrementers: `RunIdIncrementer`, `DateIncrementer`,
  `CompositeIncrementer` (`JobParametersIncrementerInterface`).
- Parameter validators: `DefaultJobParametersValidator`,
  `IdentifyingJobParametersValidator`,
  `CompositeJobParametersValidator`.
- Parameter extractors: `JobParametersExtractorInterface`,
  `DefaultJobParametersExtractor`.
- Interruption policies: `JobInterruptionPolicyInterface`,
  `SignalJobInterruptionPolicy` (`pcntl`-aware).
- Flow primitives: `FlowInterface`, `SimpleFlow`, `FlowBuilder`,
  `SimpleFlowFactory`, `Transition`, `FlowExecutionStatus`, `SplitFlow`,
  `FlowDeciderInterface`.

#### Step Layer (`Lemric\BatchProcessing\Step`)

- `StepInterface` with `execute()`, `getName()`, `isAllowStartIfComplete()`,
  `getStartLimit()`.
- `AbstractStep` base class with listener registration and event dispatch.
- `ChunkOrientedStep` — read-process-write step driving the entire chunk
  algorithm (open → loop[read/process/write/checkpoint] → close).
- `TaskletStep` wrapping `TaskletInterface` with `RepeatStatus`
  (`FINISHED` / `CONTINUABLE`) and the built-in `SystemCommandTasklet`
  (with optional `pcntl` signal handling).
- `FlowStep` — wraps a flow as a step.
- `JobStep` — runs another job as a step.
- `StepBuilder` / `StepBuilderFactory` fluent API: `chunk()`, `tasklet()`,
  `flow()`, `job()`, `partitioner()`, `workerStep()`, `gridSize()`,
  `partitionHandler()`, `faultTolerant()`, `retry()`, `noRetry()`,
  `retryPolicy()`, `backOff()`, `skip()`, `noSkip()`, `skipLimit()`,
  `skipPolicy()`, `completionPolicy()`, `streams()`, `listener()`,
  `transactionManager()`, `parametersExtractor()`, `jobLauncher()`,
  `startLimit()`, `allowStartIfComplete()`.
- Internal builder helpers: `AbstractStepBuilder`, `FaultTolerantStepBuilder`,
  `FlowStepBuilder`.

#### Item Layer (`Lemric\BatchProcessing\Item`)

- Core interfaces: `ItemReaderInterface`, `ItemProcessorInterface`,
  `ItemWriterInterface`, `ItemStreamInterface`, `CompositeItemStream`,
  `ResourceAwareItemReaderItemStreamInterface`,
  `ResourceAwareItemWriterItemStreamInterface`.
- **Readers** (`Item\Reader`): `AbstractItemReader`, `IteratorItemReader`,
  `ListItemReader`, `CallbackItemReader`, `PdoItemReader`,
  `PaginatedPdoItemReader`, `PdoPagingItemReader`, `CsvItemReader`
  (+ `CsvFieldSetMapperInterface`), `JsonItemReader`,
  `JsonLinesItemReader`, `MultiResourceItemReader`,
  `TransformingItemReader`, `RedisItemReader`, `RedisDataStructure`,
  `ScriptItemReader`, `SynchronizedItemStreamReader`, `ItemReaderAdapter`.
- Paging providers: `Item\Reader\Paging\SqlPagingQueryProviderInterface`,
  `LimitOffsetPagingQueryProvider`.
- **Processors** (`Item\Processor`): `PassThroughItemProcessor`,
  `FilteringItemProcessor`, `CompositeItemProcessor`, `ChainItemProcessor`,
  `ValidatingItemProcessor`, `BeanValidatingItemProcessor`,
  `AsyncItemProcessor`, `ScriptItemProcessor`, `ItemProcessorAdapter`.
- **Writers** (`Item\Writer`): `AbstractItemWriter`, `PdoItemWriter`,
  `PdoBatchItemWriter`, `CallbackItemWriter`, `CompositeItemWriter`,
  `ClassifierCompositeItemWriter`, `FlatFileItemWriter`,
  `JsonFileItemWriter`, `RedisItemWriter`, `MultiResourceItemWriter`,
  `AsyncItemWriter`, `ListItemWriter`, `ItemWriterAdapter`.
- **Flat-file toolkit** (`Item\FlatFile`): `LineAggregatorInterface`
  (`DelimitedLineAggregator`, `FormatterLineAggregator`,
  `PassThroughLineAggregator`), `LineTokenizerInterface`
  (`DelimitedLineTokenizer`, `FixedLengthTokenizer`,
  `PatternMatchingCompositeLineTokenizer`),
  `FieldExtractorInterface` (`PassThroughFieldExtractor`,
  `BeanWrapperFieldExtractor`), `FieldSet`, `DefaultFieldSet`,
  `FieldSetFactory`, `FieldSetMapperInterface`,
  `LineMapperInterface`, `DefaultLineMapper`.

#### Chunk Layer (`Lemric\BatchProcessing\Chunk`)

- `Chunk` with `getInputItems()`, `getOutputItems()`, `getInputCount()`,
  `getOutputCount()`, `count()`, `isEmpty()`, `isBusy()`, `getIterator()`.
- `ChunkContext` carrying `StepExecution`, `StepContribution` and the
  complete-flag.
- Providers / processors: `ChunkProviderInterface`,
  `ChunkProcessorInterface`, `SimpleChunkProvider`, `SimpleChunkProcessor`,
  `FaultTolerantChunkProvider`, `FaultTolerantChunkProcessor`
  (with **scan-mode** for per-item retry isolation).
- Completion policies: `CompletionPolicyInterface`,
  `SimpleCompletionPolicy`, `CountingCompletionPolicy`,
  `TimeoutTerminationPolicy`, `CompositeCompletionPolicy`.

#### Repository Layer (`Lemric\BatchProcessing\Repository`)

- `JobRepositoryInterface` covering job-instance, job-execution and
  step-execution persistence with: `createJobInstance`, `getJobInstance`,
  `getJobInstanceByJobNameAndParameters`, `findJobInstancesByName`,
  `getLastJobInstance`, `getJobNames`, `isJobInstanceExists`,
  `deleteJobInstance`, `createJobExecution`, `updateJobExecution`,
  `updateJobExecutionContext`, `getJobExecution`, `findJobExecutions`,
  `findRunningJobExecutions`, `getLastJobExecution`,
  `deleteJobExecution`, `add`, `update`, `updateExecutionContext`,
  `getLastStepExecution`, `getStepExecutionCount`.
- `AbstractJobRepository` shared logic.
- `InMemoryJobRepository` for tests / scripts.
- `PdoJobRepository` for production. Compatible with MySQL 8+,
  PostgreSQL 14+ and SQLite 3.37+.
- `PdoJobRepositorySchema::sqlForPdo()` DDL generator with dialect
  detection from the PDO driver name.
- `IsolationLevel` enum for the create-instance flow (default
  `SERIALIZABLE`).
- `BatchConfigurerInterface`, `DefaultBatchConfigurer`.
- DAO interfaces (`Repository\Dao`): `ExecutionContextDaoInterface`,
  `JobInstanceDaoInterface`, `JobExecutionDaoInterface`,
  `StepExecutionDaoInterface`, plus PDO implementations under
  `Repository\Dao\Pdo`.
- Sequence id incrementers (`Repository\Incrementer`):
  `DataFieldMaxValueIncrementerInterface`, `IncrementerFactory`,
  `MySQLMaxValueIncrementer`, `PostgresSequenceMaxValueIncrementer`,
  `SqliteMaxValueIncrementer`.

#### Launcher Layer (`Lemric\BatchProcessing\Launcher`)

- `JobLauncherInterface` with `run(JobInterface, JobParameters): JobExecution`.
- `AbstractJobLauncher` base class.
- `SimpleJobLauncher` — synchronous in-process launcher.
- `AsyncJobLauncher` — dispatches to a queue/bus via a user-supplied
  callable `(int $execId, string $jobName, JobParameters): void`.
- `TaskExecutorJobLauncher` — backed by a `TaskExecutor`.
- `SignalHandler` for cooperative `SIGTERM`/`SIGINT` handling.

#### Explorer Layer (`Lemric\BatchProcessing\Explorer`)

- `JobExplorerInterface` with `getJobNames`, `getJobInstance`,
  `getJobInstances`, `getJobInstanceCount`, `findJobInstancesByJobName`,
  `getJobExecution`, `getJobExecutions`, `getJobExecutionCount`,
  `findRunningJobExecutions`, `getStepExecution`.
- `SimpleJobExplorer` — direct repository queries.
- `AbstractCachedJobExplorer` — base for caching decorators.
- `CachedJobExplorer` — PSR-6 decorator.
- `SimpleCacheJobExplorer` — PSR-16 decorator.

#### Operator Layer (`Lemric\BatchProcessing\Operator`)

- `JobOperatorInterface` administrative API: `start`, `startNextInstance`,
  `stop`, `restart`, `abandon`, `getJobNames`, `getJobInstanceCount`,
  `getJobInstances`, `getJobExecutionCount`, `getRunningExecutions`,
  `getExecutions`, `getParameters`, `getSummary`,
  `getStepExecutionSummaries`, `getStepExecutionSummary`.
- `SimpleJobOperator` default implementation.

#### Registry Layer (`Lemric\BatchProcessing\Registry`)

- `JobRegistryInterface`, `JobLocatorInterface`, `JobFactoryInterface`.
- `InMemoryJobRegistry`.
- `ContainerJobRegistry` (PSR-11).
- `ContainerJobLocator` (PSR-11 lazy locator).
- `ReferenceJobFactory` for service-id factories.
- `AttributeJobScanner` discovering `#[BatchJob]` attributed classes.

#### Retry Framework (`Lemric\BatchProcessing\Retry`)

- `RetryOperations` and `RetryTemplate` (defaults: `SimpleRetryPolicy(3)`,
  `NoBackOffPolicy`).
- `RetryContext`, `RetryContextSupport`, `RetryCallback`,
  `RecoveryCallbackInterface`, `RetrySynchronizationManager`.
- **Policies** (`Retry\Policy`): `AbstractRetryPolicy`, `SimpleRetryPolicy`,
  `MaxAttemptsRetryPolicy`, `NeverRetryPolicy`, `AlwaysRetryPolicy`,
  `TimeoutRetryPolicy`, `CircuitBreakerRetryPolicy`,
  `ExceptionClassifierRetryPolicy`,
  `BinaryExceptionClassifierRetryPolicy`, `CompositeRetryPolicy`.
- **Back-off policies** (`Retry\Backoff`): `BackOffPolicyInterface`,
  `NoBackOffPolicy`, `FixedBackOffPolicy`, `ExponentialBackOffPolicy`,
  `ExponentialRandomBackOffPolicy`, `UniformRandomBackOffPolicy`.
- Retry interceptors (`Retry\Interceptor`).

#### Skip Framework (`Lemric\BatchProcessing\Skip`)

- `SkipPolicyInterface`, `LimitCheckingItemSkipPolicy`,
  `AlwaysSkipItemSkipPolicy`, `NeverSkipItemSkipPolicy`,
  `ExceptionClassifierSkipPolicy`, `ExceptionHierarchySkipPolicy`,
  `CountingSkipPolicy`, `CompositeSkipPolicy`, `SkipCounter`.

#### Listeners (`Lemric\BatchProcessing\Listener`)

- Listener interfaces: `JobExecutionListenerInterface`,
  `StepExecutionListenerInterface`, `ChunkListenerInterface`,
  `ItemReadListenerInterface`, `ItemProcessListenerInterface`,
  `ItemWriteListenerInterface`, `SkipListenerInterface`,
  `RetryListenerInterface`.
- `CompositeListener` aggregator and `StepListenerFactory`.
- `ExecutionContextPromotionListener`, `ScopeResetListener`.
- `Listener\Logging` PSR-3 listeners (`LoggingChunkListener`,
  `LoggingItemReadListener`, …).
- `Listener\Support\*` no-op base classes for every listener interface.

#### PSR-14 Events (`Lemric\BatchProcessing\Event`)

- Job: `AbstractJobEvent`, `BeforeJobEvent`, `AfterJobEvent`,
  `JobFailedEvent`.
- Step: `AbstractStepEvent`, `BeforeStepEvent`, `AfterStepEvent`,
  `StepFailedEvent`.
- Chunk: `AbstractChunkEvent`, `BeforeChunkEvent`, `AfterChunkEvent`,
  `ChunkFailedEvent`.

#### Transactions (`Lemric\BatchProcessing\Transaction`)

- `TransactionManagerInterface` (`begin`/`commit`/`rollback`).
- `PdoTransactionManager` wrapping native PDO transactions.
- `ResourcelessTransactionManager` no-op manager for memory-only
  pipelines.

#### Partitioning (`Lemric\BatchProcessing\Partition`)

- `PartitionStep`, `PartitionerInterface`, `SimplePartitioner` (numeric
  range), `ColumnRangePartitioner` (SQL `MIN`/`MAX`).
- `StepHandlerInterface`, `TaskExecutorPartitionHandler`,
  `StepExecutionSplitterInterface`, `SimpleStepExecutionSplitter`,
  `StepExecutionAggregator`, `StepLocatorInterface`,
  `ContainerStepLocator`, `PartitionNameProviderInterface`.
- Task executors: `FiberTaskExecutor` (PHP Fibers, I/O-bound),
  `ProcessTaskExecutor` (`pcntl_fork`).

#### Repeat / Core (`Lemric\BatchProcessing\Repeat`, `…\Core`)

- `RepeatOperationsInterface`, `RepeatTemplate`,
  `TaskExecutorRepeatTemplate`, `RepeatContext`,
  `RepeatListenerInterface`, `Repeat\Support\RepeatListenerSupport`,
  `Repeat\Executor\TaskExecutorInterface`, `Repeat\Executor\SyncTaskExecutor`.
- `Core\TaskExecutorInterface`, `Core\SyncTaskExecutor`,
  `Core\SimpleAsyncTaskExecutor`.

#### Scopes (`Lemric\BatchProcessing\Scope`)

- `AbstractScope`, `JobScope`, `StepScope` for late-binding components.
- `Scope\Container\ScopedContainerInterface`, `InMemoryScopedContainer`.
- `Scope\Expression\LateBindingExpressionResolverInterface`,
  `SimpleLateBindingExpressionResolver`.

#### Classifiers (`Lemric\BatchProcessing\Classifier`)

- `ClassifierInterface`, `BinaryExceptionClassifier`,
  `SubclassClassifier`, `BackToBackPatternClassifier`.

#### Attributes (`Lemric\BatchProcessing\Attribute`)

- Class-level: `#[BatchJob]`, `#[JobScope]`, `#[StepScope]`.
- Method-level listener attributes (`Attribute\Listener`):
  `#[BeforeJob]`, `#[AfterJob]`, `#[BeforeStep]`, `#[AfterStep]`,
  `#[BeforeChunk]`, `#[AfterChunk]`, `#[BeforeRead]`, `#[AfterRead]`,
  `#[BeforeProcess]`, `#[AfterProcess]`, `#[BeforeWrite]`,
  `#[AfterWrite]`, `#[OnReadError]`, `#[OnWriteError]`,
  `#[OnSkipInRead]`, `#[OnSkipInProcess]`, `#[OnSkipInWrite]`.

#### Exception Hierarchy (`Lemric\BatchProcessing\Exception`)

- Base `BatchException` (extends `\RuntimeException`).
- Job: `JobExecutionException` and the descendants
  `DuplicateJobException`, `JobExecutionAlreadyRunningException`,
  `JobExecutionNotRunningException`, `JobExecutionNotStoppedException`,
  `JobInstanceAlreadyCompleteException`, `JobParametersInvalidException`,
  `JobRestartException`, `NoSuchJobException`,
  `NoSuchJobExecutionException`, `NoSuchJobInstanceException`.
- Step: `StepExecutionException`, `JobInterruptedException`,
  `StartLimitExceededException`, `UnexpectedStepExecutionException`.
- Items: `ItemReaderException` + `NonTransientResourceException`,
  `ParseException`, `UnexpectedInputException`; `ItemWriterException` +
  `WriteFailedException`.
- Retry: `RetryException`, `ExhaustedRetryException`,
  `RetryInterruptedException`, `RetryPolicyViolationException`.
- Other: `RepositoryException` + `OptimisticLockingFailureException`,
  `FlowExecutionException`, `TransactionException`,
  `ScopeNotActiveException`, `SkipLimitExceededException`,
  `SkippableException`, `NonTransientException`, `TransientException`,
  `UnexpectedJobExecutionException`.

#### Testing Utilities (`Lemric\BatchProcessing\Testing`)

- `JobLauncherTestUtils` (`launchJob`, `launchStep`, `getRepository`).
- `JobRepositoryTestUtils`, `MetaDataInstanceFactory`, `StepRunner`.
- `MockItemReader::ofList()`, `InMemoryItemWriter`,
  `ExecutionContextTestUtils`.
- Mock factories: `RetryContextMockFactory`, `SkipContextMockFactory`.
- Scope listeners: `JobScopeTestExecutionListener`,
  `StepScopeTestExecutionListener`.
- `AssertFile` helper.

#### Symfony Bridge (`Lemric\BatchProcessing\Bridge\Symfony`)

- `BatchProcessingBundle` with `BatchProcessingExtension`,
  `Configuration` and the `BatchJobPass` compiler pass that wires
  services tagged `batch.job`, `batch.item_reader`, `batch.item_processor`
  and `batch.item_writer`.
- Configuration tree: `table_prefix`, `data_source`,
  `default_retry_policy` (`max_attempts`, `retryable_exceptions`,
  `backoff: {type, initial_interval, multiplier, max_interval}`),
  `default_skip_policy` (`skip_limit`, `skippable_exceptions`),
  `async_launcher` (`enabled`, `transport`).
- Console commands (`Bridge\Symfony\Command`):
  `batch:job:launch` (with `--param`, `--next`, `--inline`, `--async`,
  `--restart`, `--dry-run`, `--interactive`), `batch:job:list`,
  `batch:job:status`, `batch:job:stop`, `batch:job:restart`,
  `batch:job:abandon`, `batch:cleanup`, `batch:health`.
- Messenger integration: `MessengerJobDispatcher`, `RunJobMessage`,
  `RunJobMessageHandler` (with HMAC signing via `AsyncJobMessageSigner`).
- Locking: `Lock\LockingJobLauncher` (single-instance enforcement via
  `symfony/lock`).
- Web Profiler: `Profiler\BatchDataCollector`,
  `Profiler\StopwatchListener`, `Profiler\TraceableJobLauncher`,
  `Profiler\TraceableJobRepository` and Twig templates.
- Doctrine migration: `Migration\Version20250101000000CreateBatchProcessingTables`.
- Item adapters:
  `Item\Reader\DoctrineRepositoryItemReader`,
  `Item\Writer\DoctrineRepositoryItemWriter`,
  `Item\Writer\SimpleMailMessageItemWriter`.
- Validator integration: `Validator\SymfonyValidatorAdapter`.
- Serializer integration: `Serializer\SerializerJsonItemReader`,
  `Serializer\SerializerJsonItemWriter`.
- Scope integration: `Scope\ExpressionLanguageLateBindingResolver`.

#### Laravel Bridge (`Lemric\BatchProcessing\Bridge\Laravel`)

- `BatchProcessingServiceProvider` with auto-discovery.
- Default config (`config/batch_processing.php`) with `table_prefix`,
  `connection`, `default_retry`, `default_skip`, `async`.
- Migrations published under `database/migrations/` covering MySQL,
  PostgreSQL and SQLite dialects.
- Artisan commands (`Console`): `batch:job:launch`, `batch:job:list`,
  `batch:job:status`, `batch:job:stop`, `batch:job:restart`,
  `batch:job:abandon`, `batch:health`.
- Queue integration: `Queue\QueueJobDispatcher`, `Queue\RunJobQueueJob`,
  `Queue\RunStepJobMiddleware`.
- Cache decorator: `Cache\LaravelCacheJobExplorer`.
- Validator: `Validator\LaravelValidatorAdapter`.
- Transactions: `Transaction\DatabaseTransactionManager`.
- Eloquent adapters: `Item\Reader\EloquentItemReader`,
  `Item\Writer\EloquentItemWriter`.

### Dependencies

- **Required**: PHP `>= 8.4`, `psr/cache ^3.0`, `psr/container ^2.0`,
  `psr/event-dispatcher ^1.0`, `psr/log ^3.0`, `psr/simple-cache ^3.0`.
- **Suggested**: `ext-pdo`, `ext-pcntl`, `ext-redis`, `predis/predis`,
  `symfony/event-dispatcher`, `symfony/expression-language`,
  `symfony/lock`, `symfony/messenger`, `symfony/serializer`,
  `symfony/stopwatch`, `symfony/validator`, `doctrine/orm`,
  `laravel/framework`.

### Compatibility

- PHP **8.4+**.
- Symfony **6.4** and **7.x** for the Symfony Bridge.
- Laravel **11.x** and **12.x** for the Laravel Bridge.
- Databases: MySQL **8+**, PostgreSQL **14+**, SQLite **3.37+**.

### Versioning Policy

- **Patch (1.0.x)** — Bug fixes only, no API changes.
- **Minor (1.x.0)** — Backwards-compatible features (new interfaces,
  optional parameters, new listener hooks, new built-in readers/writers/
  processors).
- **Major (x.0.0)** — Breaking changes; preceded by at least one minor
  release marking the affected APIs `@deprecated`.

[Unreleased]: https://github.com/Lemric/BatchProcessing/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Lemric/BatchProcessing/releases/tag/v1.0.0

