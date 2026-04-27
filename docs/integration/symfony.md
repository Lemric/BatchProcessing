Symfony Bridge
==============

The Symfony bridge ships a `BatchProcessingBundle` with auto-configuration,
console commands, Messenger integration and Profiler hooks.

Installation
------------

```bash
composer require lemric/batch-processing
```

If Symfony Flex is not installed, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Lemric\BatchProcessing\Bridge\Symfony\BatchProcessingBundle::class => ['all' => true],
];
```

Configuration
-------------

```yaml
# config/packages/batch_processing.yaml
batch_processing:
    table_prefix: batch_
    data_source: default       # logical connection name

    default_retry_policy:
        max_attempts: 3
        retryable_exceptions:
            - \RuntimeException
        backoff:
            type: exponential        # one of: none | fixed | exponential | exponential_random | uniform_random
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

Registering Jobs & Components
-----------------------------

The bundle's `BatchJobPass` collects services tagged `batch.job`,
`batch.item_reader`, `batch.item_processor` and `batch.item_writer` and wires
them into the job registry.

### Via Service Tag

```yaml
# config/services.yaml
services:
    App\Batch\Job\ImportOrdersJob:
        tags:
            - { name: batch.job, job_name: importOrdersJob }

    App\Batch\Reader\OrderCsvReader:
        tags: [batch.item_reader]

    App\Batch\Processor\OrderProcessor:
        tags: [batch.item_processor]

    App\Batch\Writer\OrderDatabaseWriter:
        tags: [batch.item_writer]
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

Console Commands
----------------

| Command                | Description                                  |
|------------------------|----------------------------------------------|
| `batch:job:launch`     | Launch a batch job by name                   |
| `batch:job:list`       | List job executions                          |
| `batch:job:status`     | Show status of a job execution               |
| `batch:job:stop`       | Request a graceful stop of a running execution |
| `batch:job:restart`    | Restart a failed/stopped job execution       |
| `batch:job:abandon`    | Mark a stopped execution as abandoned        |
| `batch:cleanup`        | Clean up old or abandoned execution metadata |
| `batch:health`         | Show health status of the batch system       |

### Examples

```bash
# Launch
php bin/console batch:job:launch importOrdersJob \
    --param=date:2025-01-15 \
    --param=run.id:1

# Launch the next instance using the configured incrementer
php bin/console batch:job:launch importOrdersJob --next

# Force inline (sync) or async execution
php bin/console batch:job:launch importOrdersJob --inline
php bin/console batch:job:launch importOrdersJob --async

# Validate without launching
php bin/console batch:job:launch importOrdersJob --param=run.id:1 --dry-run

# List / status
php bin/console batch:job:list --name=importOrdersJob --status=FAILED
php bin/console batch:job:status 42

# Stop / restart / abandon
php bin/console batch:job:stop 42
php bin/console batch:job:restart 42
php bin/console batch:job:abandon 42

# Maintenance
php bin/console batch:cleanup
php bin/console batch:health
```

Symfony Messenger Integration
------------------------------

For asynchronous job execution the bundle ships:

* `MessengerJobDispatcher` — dispatches `RunJobMessage` to the message bus.
* `RunJobMessage` — carries `(executionId, jobName, parameters)`.
* `RunJobMessageHandler` — picks up the message on the worker side and runs the job.

### Configuration

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async_batch: '%env(MESSENGER_TRANSPORT_DSN)%'

        routing:
            'Lemric\BatchProcessing\Bridge\Symfony\Messenger\RunJobMessage': async_batch
```

Enable the async launcher in the bundle config and use the `--async` flag of
`batch:job:launch` (or call `AsyncJobLauncher` directly).

Other Bridge Components
-----------------------

The Symfony bridge also provides namespaces for additional integrations that
are picked up automatically when the related Symfony components are present:

| Namespace                       | Integration                                     |
|---------------------------------|-------------------------------------------------|
| `Bridge\Symfony\Migration`      | Doctrine Migrations for the metadata schema     |
| `Bridge\Symfony\Profiler`       | Web Profiler data collector                     |
| `Bridge\Symfony\Lock`           | `symfony/lock` integration for single-instance enforcement |
| `Bridge\Symfony\Validator`      | `symfony/validator` adapter for processors       |
| `Bridge\Symfony\Serializer`     | `symfony/serializer` adapters                    |
| `Bridge\Symfony\Scope`          | DI integration for `JobScope` / `StepScope`      |
| `Bridge\Symfony\Item`           | Symfony-specific reader/writer adapters          |

Refer to the classes inside each namespace for the precise wiring.

Next Steps
----------

* [Configuration Reference](../configuration.md)
* [Laravel Bridge](laravel.md)

