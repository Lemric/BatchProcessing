Listeners & Events (PSR-14)
===========================

Lemric BatchProcessing supports two complementary observability mechanisms:

1. **Dedicated listener interfaces** — interface-based callbacks.
2. **PSR-14 event dispatching** — any PSR-14 compatible event dispatcher.

Both mechanisms can be used simultaneously.

PSR-14 Events
-------------

Events are dispatched automatically by `AbstractJob` and `AbstractStep` when a
PSR-14 `EventDispatcherInterface` is injected via `setEventDispatcher()` (or
the builder's `eventDispatcher()` method).

| Event Class          | Dispatched when                              | Payload                    |
|----------------------|----------------------------------------------|----------------------------|
| `BeforeJobEvent`     | Before the first step executes               | `JobExecution`             |
| `AfterJobEvent`      | After the last step (success or failure)     | `JobExecution`             |
| `JobFailedEvent`     | When a job fails with an exception           | `JobExecution`, `Throwable`|
| `BeforeStepEvent`    | Before step execution                        | `StepExecution`            |
| `AfterStepEvent`     | After step execution                         | `StepExecution`            |
| `StepFailedEvent`    | When a step fails with an exception          | `StepExecution`, `Throwable`|
| `BeforeChunkEvent`   | Before reading a chunk                       | `ChunkContext`             |
| `AfterChunkEvent`    | After a chunk is committed                   | `ChunkContext`             |
| `ChunkFailedEvent`   | When a chunk fails                           | `ChunkContext`, `Throwable`|

### Subscribing to PSR-14 Events

```php
use Lemric\BatchProcessing\Event\AfterJobEvent;

$dispatcher->addListener(
    AfterJobEvent::class,
    function (AfterJobEvent $event): void {
        $execution = $event->getJobExecution();
        $logger->info('Job completed', [
            'job'    => $execution->getJobInstance()->getJobName(),
            'status' => $execution->getStatus()->value,
        ]);
    }
);
```

Listener Interfaces
-------------------

### JobExecutionListenerInterface

```php
interface JobExecutionListenerInterface
{
    public function beforeJob(JobExecution $jobExecution): void;
    public function afterJob(JobExecution $jobExecution): void;
}
```

### StepExecutionListenerInterface

```php
interface StepExecutionListenerInterface
{
    public function beforeStep(StepExecution $stepExecution): void;
    public function afterStep(StepExecution $stepExecution): ?ExitStatus;
}
```

`afterStep()` may return a custom `ExitStatus` to override the default, or
`null` to keep the existing one.

### ChunkListenerInterface

```php
interface ChunkListenerInterface
{
    public function beforeChunk(ChunkContext $context): void;
    public function afterChunk(ChunkContext $context): void;
    public function afterChunkError(ChunkContext $context, \Throwable $t): void;
}
```

### ItemReadListenerInterface

```php
interface ItemReadListenerInterface
{
    public function beforeRead(): void;
    public function afterRead(mixed $item): void;
    public function onReadError(\Throwable $t): void;
}
```

### ItemProcessListenerInterface

```php
interface ItemProcessListenerInterface
{
    public function beforeProcess(mixed $item): void;
    public function afterProcess(mixed $item, mixed $result): void;
    public function onProcessError(mixed $item, \Throwable $t): void;
}
```

### ItemWriteListenerInterface

```php
interface ItemWriteListenerInterface
{
    public function beforeWrite(Chunk $items): void;
    public function afterWrite(Chunk $items): void;
    public function onWriteError(\Throwable $t, Chunk $items): void;
}
```

### SkipListenerInterface

```php
interface SkipListenerInterface
{
    public function onSkipInRead(\Throwable $t): void;
    public function onSkipInProcess(mixed $item, \Throwable $t): void;
    public function onSkipInWrite(mixed $item, \Throwable $t): void;
}
```

### RetryListenerInterface

```php
interface RetryListenerInterface
{
    public function open(RetryContext $context): bool;
    public function onError(RetryContext $context, \Throwable $t): void;
    public function close(RetryContext $context): void;
}
```

`open()` returning `false` aborts the retry attempt.

Registering Listeners
---------------------

### Via Step Builder

```php
$step = $stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->listener($myStepListener)
    ->listener($myChunkListener)
    ->build();
```

### Via Job Builder

```php
$job = $jobBuilderFactory->get('importJob')
    ->start($step)
    ->listener($myJobListener)
    ->build();
```

A single listener object may implement multiple listener interfaces — the
framework dispatches to whichever ones are present.

Built-in Listeners
-------------------

| Listener                             | Description                                    |
|--------------------------------------|------------------------------------------------|
| `CompositeListener`                  | Aggregates multiple listeners into one         |
| `ExecutionContextPromotionListener`  | Promotes step execution context keys to the job context |
| `ScopeResetListener`                 | Resets scoped beans between steps              |
| `Logging\*`                          | PSR-3 logging listeners for the various phases |

`StepListenerFactory` (in `Lemric\BatchProcessing\Listener`) helps build
composite listeners from heterogeneous instances.

Next Steps
----------

* [PSR Compliance](psr.md)
* [Testing Utilities](testing.md)

