Laravel Bridge
==============

The Laravel bridge provides a `BatchProcessingServiceProvider` with
auto-discovery, Artisan commands, queue integration and migrations.

Installation
------------

```bash
composer require lemric/batch-processing
```

The service provider is auto-discovered via Laravel's package discovery
mechanism.

Publish Configuration & Migrations
----------------------------------

```bash
php artisan vendor:publish \
    --provider="Lemric\BatchProcessing\Bridge\Laravel\BatchProcessingServiceProvider"
```

Configuration
-------------

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

Run Migrations
--------------

```bash
php artisan migrate
```

Registering Jobs
----------------

### Via Service Container

```php
// app/Providers/AppServiceProvider.php
use Lemric\BatchProcessing\Registry\JobRegistryInterface;

public function boot(JobRegistryInterface $registry): void
{
    $registry->register('importOrdersJob', fn() => app(ImportOrdersJob::class));
}
```

### Via Attribute

```php
use Lemric\BatchProcessing\Attribute\BatchJob;
use Lemric\BatchProcessing\Job\AbstractJob;

#[BatchJob(name: 'importOrdersJob')]
final class ImportOrdersJob extends AbstractJob
{
    // ...
}
```

`AttributeJobScanner` discovers and registers attributed jobs.

Artisan Commands
----------------

The Laravel bridge exposes the same command names as the Symfony bundle:

| Command                | Description                              |
|------------------------|------------------------------------------|
| `batch:job:launch`     | Launch a job                             |
| `batch:job:list`       | List job executions                      |
| `batch:job:status`     | Show execution status                    |
| `batch:job:stop`       | Stop a running execution                 |
| `batch:job:restart`    | Restart a failed/stopped execution       |
| `batch:job:abandon`    | Abandon a stopped execution              |
| `batch:health`         | Health check                             |

### Examples

```bash
# Launch a job
php artisan batch:job:launch importOrdersJob \
    --param=date:2025-01-15 \
    --param=run.id:1

# List executions
php artisan batch:job:list --name=importOrdersJob --status=FAILED

# Show execution status
php artisan batch:job:status 42

# Stop / restart / abandon
php artisan batch:job:stop 42
php artisan batch:job:restart 42
php artisan batch:job:abandon 42
```

Queue Integration
-----------------

For async execution via Laravel queues, the bridge ships the namespace
`Bridge\Laravel\Queue` with a queue dispatcher that maps to `AsyncJobLauncher`
and a queue job class executed on workers.

### Configuration

Set in `.env`:

```ini
BATCH_ASYNC=true
BATCH_QUEUE_CONNECTION=redis
BATCH_QUEUE=batch
```

Run workers:

```bash
php artisan queue:work --queue=batch
```

Other Bridge Components
-----------------------

The Laravel bridge also provides namespaces for additional integrations:

| Namespace                       | Integration                                     |
|---------------------------------|-------------------------------------------------|
| `Bridge\Laravel\Cache`          | Cache decorators for the explorer               |
| `Bridge\Laravel\Validator`      | Laravel-validator adapter for processors        |
| `Bridge\Laravel\Transaction`    | Laravel-aware transaction manager               |
| `Bridge\Laravel\Item`           | Laravel-specific reader/writer adapters         |
| `Bridge\Laravel\database`       | Published migrations                            |
| `Bridge\Laravel\config`         | Default configuration file                      |

Next Steps
----------

* [Configuration Reference](../configuration.md)
* [Symfony Bridge](symfony.md)

