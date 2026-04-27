Architecture Overview
=====================

Lemric BatchProcessing

High-Level Overview
-------------------

```
┌──────────────────────────────────────────────────────┐
│                    Application                        │
│  (Your Jobs, Readers, Processors, Writers, Tasklets)  │
├──────────────────────────────────────────────────────┤
│                    Core Framework                     │
│  Job → Step → Chunk (read → process → write)          │
│  Retry · Skip · Transaction · Events · Listeners      │
├──────────────────────────────────────────────────────┤
│                  Infrastructure                       │
│  JobRepository · TransactionManager · Explorer        │
│  Operator · Registry · Launcher · Scope               │
├──────────────────────────────────────────────────────┤
│                  Framework Bridges                    │
│  Symfony Bundle · Laravel ServiceProvider             │
└──────────────────────────────────────────────────────┘
```

Package Structure
-----------------

```
src/
├── BatchProcessing.php              # Static facade / entry point
├── Domain/                          # Value objects: BatchStatus, ExitStatus, JobParameters, …
├── Job/                             # JobInterface, AbstractJob, SimpleJob, FlowJob, JobBuilder
│   └── Flow/                        # FlowInterface, SimpleFlow, FlowBuilder, Transition
├── Step/                            # StepInterface, AbstractStep, ChunkOrientedStep, TaskletStep
│   └── Builder/
├── Item/                            # Reader / Processor / Writer interfaces and built-ins
│   ├── Reader/                      # 18 reader implementations + Paging/
│   ├── Processor/                   # 9 processor implementations
│   ├── Writer/                      # 13 writer implementations
│   └── FlatFile/                    # Line aggregators / tokenizers / field mappers
├── Chunk/                           # Chunk, ChunkContext, completion policies, providers, processors
├── Repository/                      # JobRepositoryInterface, InMemory + PDO implementations
│   ├── Dao/                         # Internal DAOs used by PdoJobRepository
│   └── Incrementer/                 # Sequence-based id incrementers (MySQL, Postgres, SQLite)
├── Launcher/                        # SimpleJobLauncher, AsyncJobLauncher, TaskExecutorJobLauncher
├── Explorer/                        # JobExplorerInterface, SimpleJobExplorer, cached decorators
├── Operator/                        # JobOperatorInterface, SimpleJobOperator
├── Registry/                        # JobRegistryInterface, InMemory + Container variants, scanner
├── Retry/                           # RetryTemplate, policies, back-off strategies
│   ├── Policy/                      # Simple, Composite, CircuitBreaker, Classifier, …
│   ├── Backoff/                     # Fixed, Exponential, ExponentialRandom, UniformRandom, No
│   └── Interceptor/
├── Skip/                            # Skip policies + counters
├── Listener/                        # Listener interfaces + composite & support classes
│   ├── Logging/                     # PSR-3 logging listeners
│   └── Support/                     # Convenience NOOP base classes
├── Event/                           # PSR-14 events: Before/After Job, Step, Chunk + *Failed
├── Transaction/                     # TransactionManagerInterface + PDO/Resourceless impls
├── Partition/                       # PartitionStep, Partitioner, FiberTaskExecutor, ProcessTaskExecutor
├── Scope/                           # JobScope, StepScope, scoped containers, expression resolver
├── Repeat/                          # RepeatTemplate, RepeatOperations, RepeatContext
├── Classifier/                      # BinaryExceptionClassifier, SubclassClassifier, …
├── Core/                            # TaskExecutor interfaces (Sync, SimpleAsync)
├── Exception/                       # Domain exception hierarchy
├── Testing/                         # MockItemReader, InMemoryItemWriter, JobLauncherTestUtils, …
├── Attribute/                       # PHP 8 attributes: #[BatchJob], #[JobScope], #[StepScope]
│   └── Listener/                    # Per-callback attribute markers (#[BeforeJob], …)
└── Bridge/
    ├── Symfony/                     # Bundle, DI extension, console commands, Messenger, Profiler, Lock
    └── Laravel/                     # ServiceProvider, Artisan commands, Queue, Cache, Validator
```

Design Patterns
---------------

| Pattern                 | Usage                                          | Classes                                              |
|-------------------------|------------------------------------------------|------------------------------------------------------|
| Builder                 | Fluent Job/Step construction                   | `JobBuilder`, `StepBuilder`                          |
| Template Method         | Job/Step execution skeleton                    | `AbstractJob`, `AbstractStep`                        |
| Strategy                | Interchangeable retry/skip/back-off policies   | `RetryPolicyInterface`, `SkipPolicyInterface`        |
| Chain of Responsibility | Composite processor / writer pipelines         | `CompositeItemProcessor`, `CompositeItemWriter`      |
| Observer / PSR-14       | Events and listeners                           | `*ListenerInterface`, `*Event` classes               |
| Decorator               | Transforming readers, cached explorers         | `TransformingItemReader`, `CachedJobExplorer`        |
| Repository              | Execution metadata persistence                 | `JobRepositoryInterface` + implementations           |
| Composite               | Grouping listeners, writers                    | `CompositeListener`, `CompositeItemWriter`           |
| Null Object             | Pass-through processor                         | `PassThroughItemProcessor`                           |
| State Machine           | BatchStatus transitions                        | `BatchStatus` enum + `upgradeTo()` rules             |
| Adapter                 | Bridging callables / methods to interfaces     | `ItemReaderAdapter`, `ItemProcessorAdapter`, `ItemWriterAdapter` |

Execution Flow
--------------

1. **JobLauncher** creates a `JobExecution` via `JobRepository`.
2. **Job** (`SimpleJob` or `FlowJob`) iterates / routes through its **Steps**.
3. Each **Step** is either:
   * **`ChunkOrientedStep`**: reads items, processes them, writes in batches.
   * **`TaskletStep`**: executes arbitrary logic.
   * **`PartitionStep`**: dispatches a worker step against N partitions.
   * **`FlowStep`** / **`JobStep`**: composition.
4. On each committed chunk the `ExecutionContext` is persisted — enabling **restart**.
5. When all steps complete, the job's final status is written to the repository.

Next Steps
----------

* [Domain Model](domain-model.md)
* [Jobs](jobs.md)

