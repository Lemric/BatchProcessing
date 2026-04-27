Flow Jobs & Conditional Steps
=============================

A **FlowJob** supports conditional step transitions based on the **exit code** of
the preceding step (e.g. `COMPLETED`, `FAILED`, or any custom code).

Building a FlowJob
------------------

Switch the builder into flow mode with `.flow()`, then declare transitions with
`transition($from, $exitCode, $to)`:

```php
$job = $ctx['jobBuilderFactory']->get('orderPipeline')
    ->flow()
    ->start($importStep)
    ->transition($importStep, 'COMPLETED', $enrichStep)
    ->transition($importStep, 'FAILED',    $errorHandlerStep)
    ->transition($importStep, '*',         null) // wildcard, ends the flow
    ->transition($enrichStep, 'COMPLETED', $notifyStep)
    ->transition($enrichStep, 'FAILED',    null)
    ->build();
```

### Transition Rules

| Argument                 | Meaning                                              |
|--------------------------|------------------------------------------------------|
| `$from: StepInterface`   | The originating step                                 |
| `$exitCode: string`      | Exit code to match (e.g. `'COMPLETED'`, `'FAILED'`, custom, or `'*'` wildcard) |
| `$to: ?StepInterface`    | Target step. `null` ends the flow with that exit code |

FlowDeciderInterface
--------------------

For programmatic routing, register a decider via `.decider()`:

```php
use Lemric\BatchProcessing\Job\FlowDeciderInterface;
use Lemric\BatchProcessing\Domain\{JobExecution, StepExecution};

final class OrderVolumeDecider implements FlowDeciderInterface
{
    public function decide(JobExecution $jobExecution, ?StepExecution $stepExecution): string
    {
        $count = $stepExecution?->getWriteCount() ?? 0;

        return $count > 10000 ? 'HIGH_VOLUME' : 'LOW_VOLUME';
    }
}

$job = $ctx['jobBuilderFactory']->get('adaptivePipeline')
    ->flow()
    ->start($importStep)
    ->decider($importStep, new OrderVolumeDecider())
    ->transition($importStep, 'HIGH_VOLUME', $partitionedStep)
    ->transition($importStep, 'LOW_VOLUME',  $simpleStep)
    ->build();
```

When a decider is registered for a step, its `decide()` return value is used
in place of the raw exit code when matching transitions.

Parallel Splits
---------------

`split(...$steps)` registers a `SplitFlow` that executes the given steps
concurrently using PHP Fibers:

```php
$job = $ctx['jobBuilderFactory']->get('parallelPipeline')
    ->start($prepareStep)
    ->split($branchA, $branchB, $branchC)
    ->next($mergeStep)
    ->build();
```

Next Steps
----------

* [Jobs](jobs.md)
* [Steps](steps.md)
* [Partition & Parallel Processing](partition.md)

