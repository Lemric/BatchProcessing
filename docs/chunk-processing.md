Chunk-Oriented Processing
=========================

Chunk processing is the heart of the library. The framework collects items
from a reader up to a configurable `chunkSize`, processes them through a
processor, then writes the entire batch as a single transaction.

Execution Algorithm
-------------------

```
open(executionContext)
│
└── loop:
    ├── [check stopping signal]
    ├── read() × N items (up to chunkSize, with retry/skip)
    ├── process() each item (with retry/skip; null = filtered)
    ├── BEGIN TRANSACTION
    │   └── write(chunk)
    │   └── COMMIT
    ├── update(executionContext)  ← checkpoint
    └── repeat until read() returns null
│
close()
```

Each committed chunk is a **checkpoint**: the `ExecutionContext` is persisted
so that a restarted job resumes from the last committed chunk.

Configuration
-------------

```php
$step = $stepBuilderFactory->get('importStep')
    ->chunk(
        chunkSize: 500,
        reader:    $reader,
        processor: $processor,   // may be null (pass-through)
        writer:    $writer,
    )
    ->build();
```

Chunk Object
------------

`Lemric\BatchProcessing\Chunk\Chunk` collects items during a chunk cycle:

```php
$chunk->getInputItems();   // raw items collected from the reader
$chunk->getOutputItems();  // items after processing (excluding filtered)
$chunk->getInputCount();
$chunk->getOutputCount();
$chunk->count();           // alias for getInputCount()
$chunk->isEmpty();         // true when no items were read
$chunk->isBusy();          // true while still being filled
$chunk->getIterator();     // foreach support over output items
```

ChunkContext
------------

`ChunkContext` carries per-chunk state passed to listeners and tasklets:

```php
$chunkContext->getStepExecution();
$chunkContext->getStepContribution();
$chunkContext->isComplete();
$chunkContext->setComplete();
```

Completion Policies
-------------------

Control when a chunk is considered "full":

| Policy                        | Description                                 |
|-------------------------------|---------------------------------------------|
| `SimpleCompletionPolicy`      | Chunk completes after `chunkSize` items     |
| `CountingCompletionPolicy`    | Based on a running item count               |
| `TimeoutTerminationPolicy`    | Chunk completes after a time limit          |
| `CompositeCompletionPolicy`   | Combines multiple policies                  |

Override the default with `->completionPolicy(...)` on the step builder.

Fault-Tolerant Processing
--------------------------

When `.faultTolerant()` is enabled, the framework uses
`FaultTolerantChunkProcessor` and `FaultTolerantChunkProvider` which support:

1. **Retry on failure** — read/process/write are wrapped in `RetryOperations`.
2. **Scan mode** — on chunk write failure each item is retried individually
   to isolate the failing one.
3. **Skip** — items that fail after retry exhaustion are skipped according to
   the configured `SkipPolicyInterface`.

```php
$step = $stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->faultTolerant()
    ->retry(\PDOException::class, maxAttempts: 3)
    ->skip(\Lemric\BatchProcessing\Exception\SkippableException::class)
    ->skipLimit(200)
    ->backOff(new ExponentialBackOffPolicy(initial: 200, multiplier: 2.0, max: 5000))
    ->build();
```

Transaction Boundaries
-----------------------

Each chunk is wrapped in a transaction managed by the configured
`TransactionManagerInterface`:

```
BEGIN  →  write(chunk)  →  COMMIT      (success)
BEGIN  →  write(chunk)  →  ROLLBACK    (failure → scan mode)
```

The `PdoTransactionManager` wraps PDO native transactions. For environments
without a database use `ResourcelessTransactionManager`.

Next Steps
----------

* [Item Readers](item-readers.md) · [Item Writers](item-writers.md)
* [Retry Framework](retry.md) · [Skip Framework](skip.md)

