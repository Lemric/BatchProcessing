Jobs
====

A **Job** is the top-level batch entity — a container for one or more **Steps**
executed in sequence (or conditionally with flows).

JobInterface
------------

All jobs implement `Lemric\BatchProcessing\Job\JobInterface`:

```php
interface JobInterface
{
    public function getName(): string;
    public function execute(JobExecution $jobExecution): void;
    public function isRestartable(): bool;
    public function validateParameters(JobParameters $parameters): void;
}
```

SimpleJob
---------

The default implementation. Executes steps sequentially — if any step fails, the
job is marked as `FAILED`.

```php
use Lemric\BatchProcessing\Job\SimpleJob;

$job = new SimpleJob('importOrders', $jobRepository);
$job->addStep($step1);
$job->addStep($step2);
```

FlowJob
-------

Supports conditional step transitions based on the exit code of the previous
step. See [Flow Jobs & Conditional Steps](flow-jobs.md).

Building Jobs (JobBuilder)
--------------------------

Use the fluent `JobBuilder` API:

```php
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Job\RunIdIncrementer;

$jobBuilderFactory = new JobBuilderFactory($repository);

$job = $jobBuilderFactory->get('importOrdersJob')
    ->incrementer(new RunIdIncrementer())
    ->start($importStep)
    ->next($cleanupStep)
    ->listener($jobListener)
    ->preventRestart(false)
    ->build();
```

### Available Builder Methods

| Method                                                   | Purpose                                              |
|----------------------------------------------------------|------------------------------------------------------|
| `start(StepInterface)`                                   | First step (overrides any previous start)            |
| `next(StepInterface)`                                    | Append the next step (sequential mode)               |
| `flow()`                                                 | Switch to FlowJob mode (enables `transition()`)      |
| `transition(StepInterface $from, string $exitCode, ?StepInterface $to)` | Conditional transition (FlowJob only). `$to = null` ends the flow. `$exitCode` may be `'*'`. |
| `decider(StepInterface, FlowDeciderInterface)`           | Programmatic decider (FlowJob only)                  |
| `split(StepInterface ...$steps)`                         | Run multiple steps concurrently in a `SplitFlow`     |
| `listener(object)`                                       | Register a job/step listener                         |
| `incrementer(JobParametersIncrementerInterface)`         | Set a parameters incrementer                         |
| `withRunIdIncrementer(string $key = 'run.id')`           | Shortcut for `RunIdIncrementer`                      |
| `validator(JobParametersValidatorInterface)`             | Set a parameters validator                           |
| `interruptionPolicy(JobInterruptionPolicyInterface)`     | Custom interruption policy                           |
| `preventRestart(bool $value = true)`                     | Disable restart (`true` = forbid)                    |
| `allowStartIfComplete(bool $value = true)`               | Re-execute steps even if already completed           |
| `eventDispatcher(EventDispatcherInterface)`              | PSR-14 event dispatcher                              |
| `logger(LoggerInterface)`                                | PSR-3 logger                                         |
| `build(): JobInterface`                                  | Build either `SimpleJob` or `FlowJob`                |

### Static Shortcut

```php
use Lemric\BatchProcessing\BatchProcessing;

$job = BatchProcessing::job('myJob', $repository)
    ->start($step)
    ->build();
```

Job Parameters Incrementer
--------------------------

The `JobParametersIncrementerInterface` auto-generates unique identifying
parameters on each launch.

| Incrementer            | Behaviour                                       |
|------------------------|-------------------------------------------------|
| `RunIdIncrementer`     | Increments `run.id` (configurable key) by 1     |
| `DateIncrementer`      | Sets a date parameter to the current date       |
| `CompositeIncrementer` | Chains multiple incrementers                    |

Job Parameters Validation
--------------------------

| Validator                            | Purpose                                            |
|--------------------------------------|----------------------------------------------------|
| `DefaultJobParametersValidator`      | Required / optional key validation                 |
| `IdentifyingJobParametersValidator`  | Ensures specific keys are marked identifying       |
| `CompositeJobParametersValidator`    | Combine multiple validators                        |

```php
use Lemric\BatchProcessing\Job\DefaultJobParametersValidator;

$validator = new DefaultJobParametersValidator(
    requiredKeys: ['source', 'run.id'],
    optionalKeys: ['limit'],
);

$job = $builder->get('myJob')
    ->validator($validator)
    ->start($step)
    ->build();
```

Launching Jobs
--------------

```php
use Lemric\BatchProcessing\Domain\JobParameters;

$execution = $ctx['launcher']->run(
    $job,
    JobParameters::of(['run.id' => 1, 'source' => '/data/file.csv'])
);

echo $execution->getStatus()->value; // COMPLETED or FAILED
```

See [Restart Semantics](restart.md) for details on re-running jobs.

Next Steps
----------

* [Flow Jobs & Conditional Steps](flow-jobs.md)
* [Steps](steps.md)
* [Chunk-Oriented Processing](chunk-processing.md)

