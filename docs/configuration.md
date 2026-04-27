Configuration Reference
=======================

This page documents the actual configuration tree for both bridges.

Symfony Configuration
---------------------

The Symfony bundle exposes the following configuration root:

```yaml
# config/packages/batch_processing.yaml
batch_processing:
    table_prefix: batch_
    data_source: default

    default_retry_policy:
        max_attempts: 3
        retryable_exceptions:
            - \RuntimeException
        backoff:
            type: exponential        # none | fixed | exponential | exponential_random | uniform_random
            initial_interval: 200    # ms
            multiplier: 2.0
            max_interval: 10000      # ms

    default_skip_policy:
        skip_limit: 0
        skippable_exceptions: []

    async_launcher:
        enabled: false
        transport: async_batch
```

That is the **complete** configuration tree as defined by
`Bridge\Symfony\DependencyInjection\Configuration`. There are no
auto-discovery, locking or scope sub-trees in the configuration — those
features rely on standard service-tag wiring.

Laravel Configuration
---------------------

```php
// config/batch_processing.php
return [
    'table_prefix' => 'batch_',
    'connection'   => env('BATCH_DB_CONNECTION', 'mysql'),

    'default_retry' => [
        'max_attempts'         => 3,
        'retryable_exceptions' => [\RuntimeException::class],
        'backoff' => [
            'type'       => 'exponential',
            'initial'    => 200,
            'multiplier' => 2.0,
            'max'        => 10000,
        ],
    ],

    'default_skip' => [
        'skip_limit'           => 0,
        'skippable_exceptions' => [],
    ],

    'async' => [
        'enabled'    => env('BATCH_ASYNC', false),
        'connection' => env('BATCH_QUEUE_CONNECTION'),
        'queue'      => env('BATCH_QUEUE', 'batch'),
    ],
];
```

Programmatic Configuration
--------------------------

For framework-agnostic projects, configure components manually:

```php
use Lemric\BatchProcessing\Repository\PdoJobRepository;
use Lemric\BatchProcessing\Transaction\PdoTransactionManager;
use Lemric\BatchProcessing\Launcher\SimpleJobLauncher;
use Lemric\BatchProcessing\Step\StepBuilderFactory;
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Registry\InMemoryJobRegistry;
use Lemric\BatchProcessing\Operator\SimpleJobOperator;
use Lemric\BatchProcessing\Explorer\SimpleJobExplorer;

$repository = new PdoJobRepository($pdo, tablePrefix: 'batch_');
$txManager  = new PdoTransactionManager($pdo);
$launcher   = new SimpleJobLauncher($repository);
$registry   = new InMemoryJobRegistry();

$stepFactory = new StepBuilderFactory($repository, $txManager);
$jobFactory  = new JobBuilderFactory($repository);

$operator = new SimpleJobOperator($launcher, $repository, $registry);
$explorer = new SimpleJobExplorer($repository);
```

Or use the static facade:

```php
use Lemric\BatchProcessing\BatchProcessing;

// In-memory (testing / scripts)
$ctx = BatchProcessing::inMemory();

// Production with PDO
$ctx = BatchProcessing::pdo($pdo, tablePrefix: 'batch_');

// Async via dispatcher (Messenger / Queue)
$ctx = BatchProcessing::async(
    dispatcher: function (int $execId, string $jobName, JobParameters $params): void {
        $messageBus->dispatch(new RunJobMessage($execId, $jobName, $params));
    },
);
```

Environment Variables (Laravel)
-------------------------------

| Variable                  | Description                                   | Default   |
|---------------------------|-----------------------------------------------|-----------|
| `BATCH_DB_CONNECTION`     | Database connection name                      | `mysql`   |
| `BATCH_ASYNC`             | Enable async launcher                         | `false`   |
| `BATCH_QUEUE_CONNECTION`  | Queue connection                              | (none)    |
| `BATCH_QUEUE`             | Queue name                                    | `batch`   |

Next Steps
----------

* [Symfony Bridge](integration/symfony.md)
* [Laravel Bridge](integration/laravel.md)

