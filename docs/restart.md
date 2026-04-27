Restart Semantics
=================

Lemric BatchProcessing supports full restart semantics — a failed or stopped
job can be re-launched from its last checkpoint.

How Restart Works
-----------------

1. A `JobInstance` is identified by `(jobName, hash(identifyingParameters))`.
2. Re-running with the **same identifying parameters** finds the existing
   `JobInstance`:

| Existing State | Behaviour                                            |
|----------------|------------------------------------------------------|
| `COMPLETED`    | `JobInstanceAlreadyCompleteException` is thrown      |
| `STARTED`      | `JobExecutionAlreadyRunningException` is thrown      |
| `FAILED`       | A new `JobExecution` is created — job resumes       |
| `STOPPED`      | A new `JobExecution` is created — job resumes       |
| `ABANDONED`    | `JobRestartException` is thrown                      |

3. On restart each step checks for a previous `StepExecution`:
   * If the step was `COMPLETED`, it is skipped (unless
     `allowStartIfComplete` is set).
   * If the step was `FAILED` or `STOPPED`, it re-executes with the persisted
     `ExecutionContext`.

ExecutionContext & Checkpoints
------------------------------

The `ExecutionContext` is persisted after every committed chunk. Readers and
writers implementing `ItemStreamInterface` store their cursor position:

```php
public function open(ExecutionContext $ctx): void
{
    // Restore from last checkpoint
    $this->currentLine = $ctx->getInt('reader.current.line', 0);
}

public function update(ExecutionContext $ctx): void
{
    // Save checkpoint
    $ctx->put('reader.current.line', $this->currentLine);
}
```

Preventing Restart
------------------

```php
$job = $jobBuilderFactory->get('oneTimeJob')
    ->start($step)
    ->preventRestart(true)
    ->build();
```

Re-Running Completed Steps
---------------------------

By default, completed steps are skipped on restart. To force re-execution:

```php
$step = $stepBuilderFactory->get('alwaysRunStep')
    ->chunk(100, $reader, $processor, $writer)
    ->allowStartIfComplete(true)
    ->build();
```

Stopping a Running Job
----------------------

Request a graceful stop via the operator:

```php
$operator->stop($executionId); // returns bool
```

The framework checks the interruption policy before each chunk. The job will
complete the current chunk, persist the context, and exit with `STOPPED`
status.

Abandoning a Job
----------------

Mark a stopped/failed job as abandoned to permanently prevent restart:

```php
$operator->abandon($executionId); // returns the updated JobExecution
```

Signal Handling
---------------

When `ext-pcntl` is available, `SignalHandler` /
`SignalJobInterruptionPolicy` intercept `SIGTERM` and `SIGINT` signals and set
the job to `STOPPING` for a graceful shutdown.

Next Steps
----------

* [Repository & Schema](repository.md)
* [Job Operator & Explorer](operator.md)

