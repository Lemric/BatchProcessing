# Lemric BatchProcessing

[![PHP](https://img.shields.io/badge/PHP-%E2%89%A58.4-777BB4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue)](LICENSE)

Enterprise-grade batch processing library for **PHP 8.4+**, modelled after Spring Batch.
Framework-agnostic (PSR-3, PSR-11, PSR-14), with optional bridges for Symfony 7+ and Laravel 11+.

## ✨ Features

- **Job / Step / Chunk** model with full restart semantics.
- **Chunk-oriented processing** with per-chunk transactions and **scan-mode** skip handling.
- **Tasklet steps** for arbitrary unit-of-work logic.
- **Pluggable retry framework** (`SimpleRetryPolicy`, `NeverRetryPolicy`, `AlwaysRetryPolicy`,
  `ExceptionClassifierRetryPolicy`, `CompositeRetryPolicy`) with `Fixed`, `Exponential` and
  `UniformRandom` back-off policies.
- **Skip framework** (`LimitCheckingItemSkipPolicy`, `ExceptionClassifierSkipPolicy`, …).
- **Repository persistence** via the metadata schema (`InMemoryJobRepository` for tests,
  `PdoJobRepository` for MySQL 8+, PostgreSQL 14+ and SQLite 3.37+).
- **Listeners and PSR-14 events** for `Job`, `Step`, `Chunk`, `Item read/process/write`.
- **Fluent Builder API** (`JobBuilder`, `StepBuilder`).
- **Bundled readers / writers**: iterator, callback, transforming, PDO (cursor) and CSV reader;
  callback, PDO and composite writer.
- **Testing utilities**: `JobLauncherTestUtils`, `MockItemReader`, `InMemoryItemWriter`.

## Installation

```bash
composer require lemric/batch-processing
```

Requires PHP **>= 8.4**. The PDO repositories require the `pdo` extension (and a corresponding
driver – `pdo_mysql`, `pdo_pgsql` or `pdo_sqlite`).

## 60-second example

```php
use Lemric\BatchProcessing\BatchProcessing;
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Item\Reader\IteratorItemReader;
use Lemric\BatchProcessing\Item\Processor\FilteringItemProcessor;
use Lemric\BatchProcessing\Testing\InMemoryItemWriter;

$ctx = BatchProcessing::inMemory(); // wires repository, tx manager, factories, launcher

$reader    = new IteratorItemReader(range(1, 10));
$processor = new FilteringItemProcessor(fn (int $i): bool => $i % 2 === 0);
$writer    = new InMemoryItemWriter();

$step = $ctx['stepBuilderFactory']->get('demoStep')
    ->chunk(3, $reader, $processor, $writer)
    ->faultTolerant()
    ->retry(\RuntimeException::class, maxAttempts: 3)
    ->skip(\BatchProcessing\Exception\SkippableException::class)
    ->skipLimit(50)
    ->build();

$job = $ctx['jobBuilderFactory']->get('demoJob')->start($step)->build();

$execution = $ctx['launcher']->run($job, JobParameters::of(['run.id' => 1]));

echo $execution->getStatus()->value, PHP_EOL; // COMPLETED
print_r($writer->getWrittenItems());          // [2, 4, 6, 8, 10]
```

## Architecture

```
src/
├── BatchProcessing.php          # static facade (inMemory bootstrap)
├── Domain/                      # value objects: BatchStatus, ExitStatus, JobParameters, …
├── Job/                         # JobInterface, AbstractJob, SimpleJob, JobBuilder, RunIdIncrementer
├── Step/                        # StepInterface, AbstractStep, ChunkOrientedStep, TaskletStep, StepBuilder
├── Item/                        # ItemReader/Processor/Writer/Stream interfaces and built-ins
├── Chunk/                       # Chunk + ChunkContext
├── Repository/                  # JobRepositoryInterface, InMemoryJobRepository, PdoJobRepository(+Schema)
├── Launcher/                    # SimpleJobLauncher
├── Explorer/                    # SimpleJobExplorer
├── Operator/                    # SimpleJobOperator (start/stop/restart/abandon)
├── Registry/                    # JobRegistryInterface, InMemory + Container (PSR-11) impls
├── Retry/                       # RetryTemplate, policies, back-off strategies
├── Skip/                        # Skip policies
├── Listener/                    # Listener interfaces + CompositeListener
├── Event/                       # PSR-14 events: Before/AfterJob, Step, Chunk + *FailedEvent
├── Transaction/                 # TransactionManagerInterface, PdoTransactionManager, ResourcelessTransactionManager
├── Exception/                   # Domain exception hierarchy
└── Testing/                     # MockItemReader, InMemoryItemWriter, JobLauncherTestUtils
```

## Repository schema

The bundled `PdoJobRepositorySchema` produces the DDL needed to create the metadata schema for
SQLite, MySQL or PostgreSQL:

```php
use Lemric\BatchProcessing\Repository\PdoJobRepositorySchema;

$pdo = new PDO('sqlite::memory:');
foreach (PdoJobRepositorySchema::sqlForPdo($pdo, prefix: 'batch_') as $sql) {
    $pdo->exec($sql);
}
```

In production prefer running this output through your migration tool of choice (Doctrine
Migrations, Laravel migrations, …).

## Restart semantics

* A `JobInstance` is identified by `(jobName, hash(identifyingParameters))`.
* Re-running with the same identifying parameters reuses the existing instance:
  * already `COMPLETED` → `JobInstanceAlreadyCompleteException`
  * still running       → `JobExecutionAlreadyRunningException`
  * `FAILED`/`STOPPED`  → resumes (unless the job is `preventRestart`)
* `ItemStreamInterface` lets readers / writers persist their cursor into the `ExecutionContext`
  after every committed chunk – the next run picks up where the previous one left off.

## Retry & Skip

Retry and skip are completely independent strategies. The fluent builder exposes them per
step:

```php
$stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->faultTolerant()
    ->retry(\PDOException::class, maxAttempts: 3)
    ->backOff(new ExponentialBackOffPolicy(initial: 200, multiplier: 2.0, max: 5_000))
    ->skip(\Lemric\BatchProcessing\Exception\SkippableException::class)
    ->skipLimit(100)
    ->listener($myListener)
    ->build();
```

## PSR integration

| PSR     | Where                                                                               |
|---------|-------------------------------------------------------------------------------------|
| PSR-3   | All long-running components implement `LoggerAwareInterface`                        |
| PSR-11  | `ContainerJobRegistry` resolves jobs from any PSR-11 container                      |
| PSR-14  | `AbstractJob` / `AbstractStep` accept an `EventDispatcherInterface` and dispatch    |
|         | `BeforeJobEvent`, `AfterJobEvent`, `JobFailedEvent`, plus their `Step` and `Chunk`  |
|         | counterparts                                                                        |

## Testing

The repository ships with a full `phpunit.xml.dist` configuration. To run the suite:

```bash
composer test
```

PHPStan configuration is included in `phpstan.neon.dist`:

```bash
composer stan
```

## Roadmap

| Version | Scope                                                                            |
|---------|----------------------------------------------------------------------------------|
| 1.0.x   | Core (this release): Job / Step / Chunk, retry, skip, repositories, PSR support  |
| 1.1.0   | `PartitionStep` + `FiberTaskExecutor`, `AsyncJobLauncher` (Messenger/Queue)      |
| 1.2.0   | Remote partitioning (AMQP/Redis), distributed step execution                     |
| 1.3.0   | Web dashboard (Symfony UX), Prometheus / StatsD metrics exporter                 |

## License

This project is developed by Lemric and is available under a dual licensing model:

- Free for Open Source use  
  You may use this library at no cost in projects that are fully open source and released under an OSI-approved license.

- Commercial use requires a license  
  If you use this library in any proprietary, closed-source, SaaS, or commercial environment, you must obtain a commercial license from Lemric.

### Summary

| Use case                         | Allowed | Cost        |
|--------------------------------|--------|-------------|
| Open Source project            | ✅     | Free        |
| Personal non-commercial use    | ✅     | Free        |
| Commercial / SaaS / enterprise | ❌     | Paid license required |
| Closed-source project          | ❌     | Paid license required |

For commercial licensing:
dominik@labudzinski.com

See the LICENSE file for full terms.

