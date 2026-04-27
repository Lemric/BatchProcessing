Installation
============

Requirements
------------

* **PHP 8.4** or higher
* **Composer** 2.x

Optional extensions:

| Extension   | Required for                                                                       |
|-------------|------------------------------------------------------------------------------------|
| `ext-pdo`   | `PdoJobRepository`, `PdoItemReader`, `PdoItemWriter`, `PdoTransactionManager`      |
| `ext-pcntl` | Signal-safe `SystemCommandTasklet`, graceful `SIGTERM`/`SIGINT` handling           |
| `ext-redis` | `RedisItemReader` / `RedisItemWriter` (alternatively use `predis/predis`)          |

Optional libraries (see `composer.json#suggest`):

| Package                          | Enables                                                |
|----------------------------------|--------------------------------------------------------|
| `symfony/event-dispatcher`       | A fully-featured PSR-14 dispatcher                      |
| `symfony/messenger`              | Asynchronous job execution via Messenger                |
| `symfony/lock`                   | Single-instance enforcement via locking launcher        |
| `symfony/validator`              | Bean-validating processor backed by Symfony Validator   |
| `symfony/serializer`             | JSON reader/writer adapters                             |
| `symfony/stopwatch`              | Stopwatch / Profiler integration                        |
| `symfony/expression-language`    | Late-binding expressions in scoped components           |
| `doctrine/orm`                   | Doctrine repository reader/writer                       |
| `laravel/framework`              | Laravel Bridge integration                              |

Install via Composer
--------------------

```bash
composer require lemric/batch-processing
```

### Symfony Bridge

If you are using Symfony, the bundle is auto-discovered when `symfony/flex` is
installed. Otherwise register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Lemric\BatchProcessing\Bridge\Symfony\BatchProcessingBundle::class => ['all' => true],
];
```

### Laravel Bridge

The service provider is auto-discovered by Laravel's package discovery. To
publish the configuration and migrations:

```bash
php artisan vendor:publish \
    --provider="Lemric\BatchProcessing\Bridge\Laravel\BatchProcessingServiceProvider"
```

Verify Installation
-------------------

```bash
composer show lemric/batch-processing
```

For Symfony projects you can verify the bundle is loaded:

```bash
php bin/console debug:container --tag=batch.job
```

Next Steps
----------

* [Quick Start](quick-start.md)
* [Architecture Overview](architecture.md)

