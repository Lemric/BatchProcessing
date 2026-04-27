Domain Model
============

The domain model lives in the `Lemric\BatchProcessing\Domain` namespace and
consists of immutable value objects and stateful execution records.

BatchStatus
-----------

A backed enum representing the execution state of a Job or Step:

```php
enum BatchStatus: string
{
    case STARTING  = 'STARTING';
    case STARTED   = 'STARTED';
    case STOPPING  = 'STOPPING';
    case STOPPED   = 'STOPPED';
    case FAILED    = 'FAILED';
    case COMPLETED = 'COMPLETED';
    case ABANDONED = 'ABANDONED';
}
```

### Methods

| Method                            | Description                                   |
|-----------------------------------|-----------------------------------------------|
| `isRunning(): bool`               | `true` for `STARTING`, `STARTED`, `STOPPING`  |
| `isUnsuccessful(): bool`          | `true` for `FAILED`, `ABANDONED`              |
| `isGreaterThan(self): bool`       | Compares ordinals                             |
| `ordinal(): int`                  | Numeric ranking (used for upgrade rules)      |
| `upgradeTo(self $newStatus): self`| Returns the higher-priority status            |

ExitStatus
----------

A value object carrying an exit code and an optional description. Common
instances are exposed as **static class properties**:

```php
use Lemric\BatchProcessing\Domain\ExitStatus;

ExitStatus::$UNKNOWN;
ExitStatus::$EXECUTING;
ExitStatus::$COMPLETED;
ExitStatus::$NOOP;
ExitStatus::$FAILED;
ExitStatus::$STOPPED;

// String constants for the underlying codes:
ExitStatus::COMPLETED_CODE; // 'COMPLETED'
ExitStatus::FAILED_CODE;    // 'FAILED'
// …

// Custom exit status:
$exit = new ExitStatus('CUSTOM', 'Additional description');

$exit->getExitCode();        // 'CUSTOM'
$exit->getExitDescription(); // 'Additional description'
$exit->and(ExitStatus::$FAILED);
$exit->replaceExitCode('REPLACED');
$exit->addExitDescription('More info');
$exit->compareTo(ExitStatus::$COMPLETED);
$exit->isRunning(); // EXECUTING / UNKNOWN
```

JobParameters
-------------

An immutable collection of parameters passed when launching a job. Parameters
with `identifying = true` (the default) form the `JobInstance` key.

```php
use Lemric\BatchProcessing\Domain\JobParameters;

$params = JobParameters::of([
    'run.id'      => 1,
    'import.date' => new \DateTimeImmutable('2025-01-15'),
    'source'      => '/var/data/orders.csv',
]);

$params->getLong('run.id');                // 1
$params->getString('source');              // '/var/data/orders.csv'
$params->getDate('import.date');           // DateTimeImmutable
$params->getDouble('threshold', 0.0);
$params->getIdentifyingParameters();       // array<string, JobParameter>
$params->identifyingOnly();                // JobParameters with identifying only
$params->toIdentifyingString();            // serialized identity
$params->toJobKey();                       // hashed instance key
$params->isEmpty();
$params->count();
$params->get('run.id');                    // ?JobParameter
```

### JobParameter

```php
use Lemric\BatchProcessing\Domain\JobParameter;

JobParameter::ofString('source', '/data/file.csv', identifying: true);
JobParameter::ofLong('run.id', 42);
JobParameter::ofDouble('threshold', 0.95);
JobParameter::ofDate('date', new \DateTimeImmutable());

// Type constants
JobParameter::TYPE_STRING; // 'STRING'
JobParameter::TYPE_LONG;   // 'LONG'
JobParameter::TYPE_DOUBLE; // 'DOUBLE'
JobParameter::TYPE_DATE;   // 'DATE'
```

### JobParametersBuilder

A fluent builder:

```php
use Lemric\BatchProcessing\Domain\JobParametersBuilder;

$params = (new JobParametersBuilder())
    ->addString('source', '/data/file.csv')
    ->addLong('run.id', 1)
    ->addDouble('threshold', 0.5, identifying: false)
    ->addDate('date', new \DateTimeImmutable())
    ->toJobParameters();

// Compose with an existing JobParameters:
$params = (new JobParametersBuilder($base))
    ->addJobParameters($extra)
    ->removeParameter('debug')
    ->toJobParameters();
```

JobInstance
-----------

A logical batch job identified by `(jobName, hash(identifyingParameters))`.
Multiple `JobExecution` records may exist for one `JobInstance`.

JobExecution
------------

Represents a single attempt to run a `JobInstance`. Tracks:

* `BatchStatus` and `ExitStatus`
* Start/end timestamps
* List of `StepExecution` records
* `ExecutionContext` (job-level)
* Failure exceptions

StepExecution
-------------

Tracks execution statistics for a single step:

| Counter             | Description                              |
|---------------------|------------------------------------------|
| `readCount`         | Number of items read                     |
| `writeCount`        | Number of items written                  |
| `filterCount`       | Items filtered by the processor          |
| `commitCount`       | Number of committed chunks               |
| `rollbackCount`     | Number of rolled-back chunks             |
| `readSkipCount`     | Items skipped during read                |
| `processSkipCount`  | Items skipped during processing          |
| `writeSkipCount`    | Items skipped during write               |

Use `getSummary()` for a human-readable snapshot:

```text
StepExecution[importStep]: read=1000, write=980, commit=10, rollback=1, skip=20 (r=5,p=10,w=5)
```

ExecutionContext
----------------

A key-value map persisted to the repository after each committed chunk.
Essential for the restart mechanism — readers and writers store cursor
positions here.

```php
use Lemric\BatchProcessing\Domain\ExecutionContext;

$ctx = $stepExecution->getExecutionContext();

$ctx->put('cursor.position', 5000);
$ctx->putIfAbsent('first.id', 1);
$ctx->getInt('cursor.position');
$ctx->getString('cursor.label', 'default');
$ctx->getFloat('threshold');
$ctx->getBool('seen');
$ctx->containsKey('cursor.position');
$ctx->containsValue(5000);
$ctx->isDirty();
$ctx->isDirty('cursor.position');
$ctx->clearDirtyFlag();
$ctx->merge($otherCtx);
$ctx->toArray();

ExecutionContext::fromArray(['k' => 'v']);
ExecutionContext::immutable($source);

// Reserved keys used by the framework
ExecutionContext::READ_COUNT;    // 'batch.read_count'
ExecutionContext::WRITE_COUNT;   // 'batch.write_count'
ExecutionContext::FILTER_COUNT;  // 'batch.filter_count'
ExecutionContext::READ_SKIP;     // 'batch.read_skip'
ExecutionContext::WRITE_SKIP;    // 'batch.write_skip'
ExecutionContext::PROCESS_SKIP;  // 'batch.process_skip'
```

StepContribution
----------------

A per-chunk metric accumulator merged into `StepExecution` after each committed
chunk:

```php
$contribution->incrementReadCount(1);
$contribution->incrementWriteCount(10);
$contribution->incrementFilterCount(2);
$contribution->incrementReadSkipCount();
$contribution->incrementProcessSkipCount();
$contribution->incrementWriteSkipCount();
$contribution->setExitStatus(ExitStatus::$COMPLETED);
$contribution->apply();                 // flush into StepExecution
$contribution->combine($otherContribution);
```

DefaultJobParametersConverter
-----------------------------

Converts between `JobParameters` and serialized representations (used by
console commands when parsing `--param=key:value` options).

Next Steps
----------

* [Jobs](jobs.md) · [Steps](steps.md)

