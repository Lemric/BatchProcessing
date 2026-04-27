Testing Utilities
=================

The `Lemric\BatchProcessing\Testing` namespace provides utilities for unit and
integration testing of jobs and steps without bootstrapping full infrastructure.

JobLauncherTestUtils
--------------------

A drop-in launcher for tests that runs jobs against an in-memory repository:

```php
use Lemric\BatchProcessing\Testing\JobLauncherTestUtils;
use Lemric\BatchProcessing\Repository\InMemoryJobRepository;
use Lemric\BatchProcessing\Domain\{JobParameters, ExecutionContext};

// Optional: pass your own repository, otherwise an InMemoryJobRepository is used
$utils = new JobLauncherTestUtils($repository);

$jobExecution  = $utils->launchJob($job, JobParameters::of(['run.id' => 1]));
$stepExecution = $utils->launchStep($step, $executionContext);

$utils->getRepository(); // access the underlying repository
```

`launchStep()` accepts an optional `ExecutionContext` to seed the step (no
`JobParameters` argument).

InMemoryItemWriter
------------------

Captures all written items for assertions. Useful as a stand-in writer:

```php
use Lemric\BatchProcessing\Testing\InMemoryItemWriter;

$writer = new InMemoryItemWriter();

// after job execution:
$writer->getWrittenItems(); // array of all items written
$writer->getWriteCount();
$writer->reset();
$writer->disableFailures();
```

MockItemReader
--------------

Creates a reader from a fixed list of items:

```php
use Lemric\BatchProcessing\Testing\MockItemReader;

$reader = MockItemReader::ofList([
    new Order(id: 1, total: 99.99),
    new Order(id: 2, total: 149.00),
    new Order(id: 3, total: 0.0),
]);
```

MetaDataInstanceFactory
-----------------------

Creates pre-populated `JobInstance` and `JobExecution` records for tests
(see the class for the full helper API).

JobRepositoryTestUtils
----------------------

Helpers for direct repository manipulation in tests (cleanup, seeding, …).

ExecutionContextTestUtils
-------------------------

Convenience helpers for building/inspecting `ExecutionContext` instances in
restart tests.

StepRunner
----------

Runs a single step in isolation. See the class for the exact signature in your
version.

Mock Factories
--------------

| Factory                       | Purpose                                  |
|-------------------------------|------------------------------------------|
| `RetryContextMockFactory`     | Build mock `RetryContext` for unit tests |
| `SkipContextMockFactory`      | Build mock skip-counter contexts         |

Scope Test Listeners
--------------------

For testing scoped components:

| Listener                               | Purpose                                 |
|----------------------------------------|-----------------------------------------|
| `JobScopeTestExecutionListener`        | Activates `JobScope` during tests       |
| `StepScopeTestExecutionListener`       | Activates `StepScope` during tests      |

AssertFile
----------

Static assertions for file outputs (CSV, JSON, …). Inspect the class for the
available helpers in your version.

Complete Example
----------------

```php
use PHPUnit\Framework\TestCase;
use Lemric\BatchProcessing\BatchProcessing;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters};
use Lemric\BatchProcessing\Testing\{InMemoryItemWriter, MockItemReader};

final class ImportOrdersJobTest extends TestCase
{
    public function testImportFiltersZeroAmountOrders(): void
    {
        // Arrange
        $ctx = BatchProcessing::inMemory();

        $reader = MockItemReader::ofList([
            new Order(1, 99.99),
            new Order(2, 149.00),
            new Order(3, 0.0),     // will be filtered
        ]);

        $processor = new OrderProcessor(minTotal: 10.0);
        $writer    = new InMemoryItemWriter();

        $step = $ctx['stepBuilderFactory']->get('importStep')
            ->chunk(10, $reader, $processor, $writer)
            ->build();

        $job = $ctx['jobBuilderFactory']->get('importJob')
            ->start($step)
            ->build();

        // Act
        $execution = $ctx['launcher']->run($job, JobParameters::of(['run.id' => 1]));

        // Assert
        self::assertSame(BatchStatus::COMPLETED, $execution->getStatus());

        $stepExecution = $execution->getStepExecutions()[0];
        self::assertSame(3, $stepExecution->getReadCount());
        self::assertSame(2, $stepExecution->getWriteCount());
        self::assertSame(1, $stepExecution->getFilterCount());

        self::assertCount(2, $writer->getWrittenItems());
    }
}
```

Next Steps
----------

* [Restart Semantics](restart.md)
* [Performance & Best Practices](performance.md)

