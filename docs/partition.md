Partition & Parallel Processing
================================

`PartitionStep` divides data processing into N independent partitions executed
in parallel. Each partition runs as a separate `StepExecution` with its own
`ExecutionContext`.

PartitionerInterface
--------------------

```php
namespace Lemric\BatchProcessing\Partition;

interface PartitionerInterface
{
    /**
     * @param  int $gridSize  Number of requested partitions
     * @return array<string, ExecutionContext>  Map of partition name → context
     */
    public function partition(int $gridSize): array;
}
```

Built-in Partitioners
---------------------

### SimplePartitioner

Splits a numeric `[min, max]` range evenly into `gridSize` partitions. Each
partition's `ExecutionContext` receives the partition bounds.

```php
use Lemric\BatchProcessing\Partition\SimplePartitioner;

$partitioner = new SimplePartitioner(min: 1, max: 1_000_000);
```

### ColumnRangePartitioner

Computes `MIN(column)` / `MAX(column)` from a SQL table and splits that range
into `gridSize` partitions:

```php
use Lemric\BatchProcessing\Partition\ColumnRangePartitioner;

$partitioner = new ColumnRangePartitioner(
    pdo: $pdo,
    table: 'orders',
    column: 'id',
);
```

Building a PartitionStep
------------------------

```php
$workerStep = $stepBuilderFactory->get('importWorker')
    ->chunk(500, $reader, $processor, $writer)
    ->build();

$partitionStep = $stepBuilderFactory->get('partitionedImport')
    ->partitioner(new ColumnRangePartitioner($pdo, 'orders', 'id'))
    ->workerStep($workerStep)
    ->gridSize(8)
    ->build();
```

You may inject a custom `StepHandlerInterface` via `partitionHandler()` to
control how partitions are dispatched. By default the framework uses
`TaskExecutorPartitionHandler`.

Task Executors
--------------

Task executors live in the `Partition` and `Core` namespaces:

| Executor                  | Description                                         |
|---------------------------|-----------------------------------------------------|
| `FiberTaskExecutor`       | PHP 8.1+ Fibers for I/O-bound steps                 |
| `ProcessTaskExecutor`     | System processes via `pcntl_fork`                   |
| `SyncTaskExecutor`        | Sequential execution (no parallelism, for testing)  |
| `SimpleAsyncTaskExecutor` | Lightweight async wrapper with concurrency limit    |

```php
use Lemric\BatchProcessing\Partition\FiberTaskExecutor;

$executor = new FiberTaskExecutor(maxConcurrent: 8);
```

The handler picks up the executor — see `TaskExecutorPartitionHandler` and
`StepLocatorInterface` (`ContainerStepLocator` for PSR-11 container resolution).

Aggregation
-----------

`StepExecutionAggregator` merges statistics from all partition executions back
into the parent `PartitionStep`'s `StepExecution`.

Next Steps
----------

* [Steps](steps.md)
* [Performance & Best Practices](performance.md)

