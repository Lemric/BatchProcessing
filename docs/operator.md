Job Operator & Explorer
=======================

JobOperatorInterface
--------------------

An administrative API for managing the lifecycle of batch jobs:

```php
namespace Lemric\BatchProcessing\Operator;

interface JobOperatorInterface
{
    public function start(string $jobName, JobParameters $parameters): int;
    public function startNextInstance(string $jobName): int;
    public function stop(int $executionId): bool;
    public function restart(int $executionId): int;
    public function abandon(int $executionId): JobExecution;

    public function getJobNames(): array;
    public function getJobInstanceCount(string $jobName): int;
    public function getJobInstances(string $jobName, int $start, int $count): array;
    public function getJobExecutionCount(string $jobName): int;
    public function getRunningExecutions(string $jobName): array;
    public function getExecutions(int $instanceId): array;
    public function getParameters(int $executionId): string;
    public function getSummary(int $executionId): string;
    public function getStepExecutionSummaries(int $executionId): array;
    public function getStepExecutionSummary(int $jobExecutionId, int $stepExecutionId): string;
}
```

The default `SimpleJobOperator` implementation requires three collaborators:

```php
use Lemric\BatchProcessing\Operator\SimpleJobOperator;

$operator = new SimpleJobOperator($launcher, $repository, $registry);

// Start a job
$executionId = $operator->start('importOrdersJob', $parameters);

// Stop a running job (returns true if a stop was requested)
$operator->stop($executionId);

// Restart a failed job — returns the new JobExecution id
$newExecutionId = $operator->restart($executionId);

// Abandon a stopped/failed job — returns the updated JobExecution
$execution = $operator->abandon($executionId);

// Use the registered incrementer to launch the next instance
$nextId = $operator->startNextInstance('importOrdersJob');
```

JobExplorerInterface
--------------------

A read-only query API for batch execution metadata:

```php
namespace Lemric\BatchProcessing\Explorer;

interface JobExplorerInterface
{
    public function getJobNames(): array;
    public function getJobInstance(int $instanceId): ?JobInstance;
    public function getJobInstances(string $jobName, int $start = 0, int $count = 20): array;
    public function getJobInstanceCount(string $jobName): int;
    public function findJobInstancesByJobName(string $jobName, int $start, int $count): array;

    public function getJobExecution(int $executionId): ?JobExecution;
    public function getJobExecutions(JobInstance $instance): array;
    public function getJobExecutionCount(string $jobName): int;
    public function findRunningJobExecutions(string $jobName): array;

    public function getStepExecution(int $jobExecutionId, int $stepExecutionId): ?StepExecution;
}
```

### Implementations

| Class                    | Description                                  |
|--------------------------|----------------------------------------------|
| `SimpleJobExplorer`      | Direct repository queries                    |
| `AbstractCachedJobExplorer` | Base class for cached decorators           |
| `CachedJobExplorer`      | PSR-6 cache decorator                        |
| `SimpleCacheJobExplorer` | PSR-16 SimpleCache decorator                 |

```php
use Lemric\BatchProcessing\Explorer\SimpleJobExplorer;

$explorer = new SimpleJobExplorer($repository);

$running = $explorer->findRunningJobExecutions('importOrdersJob');
$execution = $explorer->getJobExecution(42);
```

Job Registry
------------

The registry maps job names to `JobInterface` instances or factories.

| Class                  | Description                                  |
|------------------------|----------------------------------------------|
| `JobRegistryInterface` | The contract                                 |
| `InMemoryJobRegistry`  | Simple in-memory implementation              |
| `ContainerJobRegistry` | Registry backed by a PSR-11 container        |
| `ContainerJobLocator`  | Lazy locator backed by a PSR-11 container    |
| `ReferenceJobFactory`  | `JobFactoryInterface` returning a service id |
| `AttributeJobScanner`  | Discovers `#[BatchJob]` attributed classes   |

`register()` accepts either a built `JobInterface` or a `callable` factory:

```php
use Lemric\BatchProcessing\Registry\InMemoryJobRegistry;

$registry = new InMemoryJobRegistry();
$registry->register('importJob', $job);
$registry->register('lazyJob', fn() => $container->get(LazyJob::class));

$job = $registry->getJob('importJob');
$registry->hasJob('importJob');
$registry->getJobNames();
```

### Attribute-Based Discovery

Mark a job class with `#[BatchJob]`:

```php
use Lemric\BatchProcessing\Attribute\BatchJob;

#[BatchJob(name: 'importOrdersJob')]
final class ImportOrdersJob extends AbstractJob
{
    // ...
}
```

`AttributeJobScanner` discovers and registers attributed jobs.

Next Steps
----------

* [Restart Semantics](restart.md)
* [Symfony Bridge](integration/symfony.md)

