Repository & Schema
===================

The **JobRepository** is the central persistence layer for all execution
metadata (job instances, executions, step executions, execution contexts).

JobRepositoryInterface
----------------------

```php
namespace Lemric\BatchProcessing\Repository;

interface JobRepositoryInterface
{
    // Job Instance
    public function createJobInstance(string $jobName, JobParameters $parameters): JobInstance;
    public function getJobInstance(int $instanceId): ?JobInstance;
    public function getJobInstanceByJobNameAndParameters(string $jobName, JobParameters $parameters): ?JobInstance;
    public function findJobInstancesByName(string $jobName, int $start = 0, int $count = 20): array;
    public function getLastJobInstance(string $jobName): ?JobInstance;
    public function getJobNames(): array;
    public function isJobInstanceExists(string $jobName, JobParameters $parameters): bool;
    public function deleteJobInstance(int $instanceId): void;

    // Job Execution
    public function createJobExecution(JobInstance $instance, JobParameters $parameters): JobExecution;
    public function updateJobExecution(JobExecution $jobExecution): void;
    public function updateJobExecutionContext(JobExecution $jobExecution): void;
    public function getJobExecution(int $executionId): ?JobExecution;
    public function findJobExecutions(JobInstance $instance): array;
    public function findRunningJobExecutions(string $jobName): array;
    public function getLastJobExecution(JobInstance $instance): ?JobExecution;
    public function deleteJobExecution(int $executionId): void;

    // Step Execution
    public function add(StepExecution $stepExecution): void;
    public function update(StepExecution $stepExecution): void;
    public function updateExecutionContext(StepExecution $stepExecution): void;
    public function getLastStepExecution(JobInstance $instance, string $stepName): ?StepExecution;
    public function getStepExecutionCount(JobInstance $instance, string $stepName): int;
}
```

Implementations
---------------

### InMemoryJobRepository

Stores all data in PHP arrays. Suitable for tests and stateless scripts:

```php
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;

$repository = new InMemoryJobRepository();
```

### PdoJobRepository

Production-grade PDO implementation. Supports MySQL 8+, PostgreSQL 14+ and
SQLite 3.37+:

```php
use Lemric\BatchProcessing\Repository\{PdoJobRepository, IsolationLevel};

$pdo = new PDO('mysql:host=127.0.0.1;dbname=myapp', 'user', 'pass');

$repository = new PdoJobRepository(
    pdo: $pdo,
    tablePrefix: 'batch_',
    isolationLevelForCreate: IsolationLevel::SERIALIZABLE,
);
```

Database Schema
---------------

Use `PdoJobRepositorySchema` to generate the DDL. The class chooses the dialect
from the PDO driver name:

```php
use Lemric\BatchProcessing\Repository\PdoJobRepositorySchema;

$pdo = new PDO('sqlite::memory:');

foreach (PdoJobRepositorySchema::sqlForPdo($pdo, prefix: 'batch_') as $sql) {
    $pdo->exec($sql);
}
```

The schema creates the following tables:

| Table                             | Purpose                                  |
|-----------------------------------|------------------------------------------|
| `batch_job_instance`              | Logical job instances (name + key hash)  |
| `batch_job_execution`             | Individual execution attempts            |
| `batch_job_execution_params`      | Parameters of each execution             |
| `batch_job_execution_context`     | Job-level execution context (JSON)       |
| `batch_step_execution`            | Step execution records and counters      |
| `batch_step_execution_context`    | Step-level execution context (JSON)      |

> The exact DDL is dialect-specific. Always inspect the generated SQL or run
> `PdoJobRepositorySchema::sqlForPdo()` in a migration.

Migration Integration
---------------------

In production, run the DDL through your migration tool:

* **Symfony** — see the migration class in `Bridge\Symfony\Migration`.
* **Laravel** — publish migrations with `vendor:publish` and run `php artisan migrate`.
* **Standalone** — execute the output of `PdoJobRepositorySchema::sqlForPdo()` manually.

Isolation Level
---------------

`PdoJobRepository` accepts an `IsolationLevel` for the create-instance flow.
The enum exposes the standard SQL isolation levels (`READ_COMMITTED`,
`REPEATABLE_READ`, `SERIALIZABLE`, …). The default is `SERIALIZABLE` to
prevent duplicate `JobInstance` rows when the same identifying parameters are
launched concurrently.

Other Components
----------------

| Class                          | Purpose                                       |
|--------------------------------|-----------------------------------------------|
| `AbstractJobRepository`        | Common logic shared by both implementations   |
| `BatchConfigurerInterface`     | Centralized configuration contract            |
| `DefaultBatchConfigurer`       | Default configurer wiring                     |
| `Repository\Incrementer\*`     | Helpers for sequence-based id generation      |
| `Repository\Dao\*`             | Internal DAOs used by `PdoJobRepository`      |

Next Steps
----------

* [Transactions](transactions.md)
* [Restart Semantics](restart.md)

