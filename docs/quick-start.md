Quick Start
===========

This guide walks you through a minimal working example in under 60 seconds.

Minimal Example (In-Memory)
----------------------------

```php
<?php

use Lemric\BatchProcessing\BatchProcessing;
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Item\Reader\IteratorItemReader;
use Lemric\BatchProcessing\Item\Processor\FilteringItemProcessor;
use Lemric\BatchProcessing\Testing\InMemoryItemWriter;

// 1. Bootstrap — wires repository, transaction manager, factories, launcher
$ctx = BatchProcessing::inMemory();

// 2. Define components
$reader    = new IteratorItemReader(range(1, 10));
$processor = new FilteringItemProcessor(fn (int $i): bool => $i % 2 === 0);
$writer    = new InMemoryItemWriter();

// 3. Build a step
$step = $ctx['stepBuilderFactory']->get('filterEvenNumbers')
    ->chunk(3, $reader, $processor, $writer)
    ->build();

// 4. Build a job
$job = $ctx['jobBuilderFactory']->get('demoJob')
    ->start($step)
    ->build();

// 5. Launch
$execution = $ctx['launcher']->run($job, JobParameters::of(['run.id' => 1]));

echo $execution->getStatus()->value, PHP_EOL; // COMPLETED
print_r($writer->getWrittenItems());          // [2, 4, 6, 8, 10]
```

Fault-Tolerant Example
-----------------------

Add retry and skip policies to handle transient failures:

```php
$step = $ctx['stepBuilderFactory']->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->faultTolerant()
    ->retry(\RuntimeException::class, maxAttempts: 3)
    ->skip(\Lemric\BatchProcessing\Exception\SkippableException::class)
    ->skipLimit(50)
    ->build();
```

Production Example (PDO)
-------------------------

For production workloads, use a real database:

```php
use Lemric\BatchProcessing\BatchProcessing;
use Lemric\BatchProcessing\Repository\PdoJobRepositorySchema;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=myapp', 'user', 'pass');

// Provision the metadata schema (run once, e.g. in a migration)
foreach (PdoJobRepositorySchema::sqlForPdo($pdo, prefix: 'batch_') as $sql) {
    $pdo->exec($sql);
}

// Bootstrap the environment
$ctx = BatchProcessing::pdo($pdo, tablePrefix: 'batch_');

// Build and launch jobs as shown above
$execution = $ctx['launcher']->run($job, $parameters);
```

The bootstrap context returned by `BatchProcessing::inMemory()`,
`BatchProcessing::pdo()` and `BatchProcessing::async()` always contains:

| Key                  | Type                                              |
|----------------------|---------------------------------------------------|
| `repository`         | `JobRepositoryInterface`                          |
| `transactionManager` | `TransactionManagerInterface`                     |
| `stepBuilderFactory` | `StepBuilderFactory`                              |
| `jobBuilderFactory`  | `JobBuilderFactory`                               |
| `launcher`           | `JobLauncherInterface`                            |
| `registry`           | `JobRegistryInterface`                            |
| `operator`           | `JobOperatorInterface`                            |
| `explorer`           | `JobExplorerInterface`                            |

Next Steps
----------

* [Architecture Overview](architecture.md)
* [Jobs](jobs.md)
* [Chunk-Oriented Processing](chunk-processing.md)

