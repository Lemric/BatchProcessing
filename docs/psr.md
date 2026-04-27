PSR Compliance
==============

Lemric BatchProcessing is built around PHP-FIG standards for maximum
interoperability.

Supported PSRs
--------------

| PSR    | Title                       | Where it is used                                                  |
|--------|-----------------------------|-------------------------------------------------------------------|
| PSR-3  | Logger Interface            | All long-running components implement `LoggerAwareInterface`      |
| PSR-6  | Caching Interface           | `CachedJobExplorer` decorator                                     |
| PSR-11 | Container Interface         | `ContainerJobRegistry`, `ContainerStepLocator`, `ContainerJobLocator` |
| PSR-14 | Event Dispatcher            | All `Event\*` classes; injected into `AbstractJob`/`AbstractStep` |
| PSR-16 | Simple Cache                | `SimpleCacheJobExplorer` decorator                                |

PSR-3 Logger
------------

Components that perform long-running work expose `setLogger()` (e.g.
`AbstractJob::setLogger()`, `AbstractStep::setLogger()`):

```php
$step->setLogger($logger);
$repository->setLogger($logger); // when supported
```

When a logger is provided, the framework emits structured log messages for:

* Job/step lifecycle transitions
* Retry attempts (via the retry listener)
* Skip events
* Chunk commit/rollback events

The `Lemric\BatchProcessing\Listener\Logging` namespace ships ready-made
PSR-3 logging listeners (`LoggingChunkListener`, `LoggingItemReadListener`,
…).

PSR-11 Container
----------------

Resolve jobs from any PSR-11 container:

```php
use Lemric\BatchProcessing\Registry\ContainerJobRegistry;
use Psr\Container\ContainerInterface;

$registry = new ContainerJobRegistry($container);
$job = $registry->getJob('importOrdersJob');
```

`ContainerStepLocator` provides the same lookup pattern for partition workers,
and `ContainerJobLocator` is a lazy locator variant.

PSR-14 Event Dispatcher
------------------------

Inject any PSR-14 dispatcher into `AbstractJob`/`AbstractStep` via
`setEventDispatcher()` (or via the builder's `eventDispatcher()` method):

```php
use Psr\EventDispatcher\EventDispatcherInterface;
use Lemric\BatchProcessing\Event\AfterJobEvent;

$dispatcher->addListener(
    AfterJobEvent::class,
    function (AfterJobEvent $event): void {
        $execution = $event->getJobExecution();
        $logger->info('Job finished', [
            'status' => $execution->getStatus()->value,
            'reads'  => array_sum(array_map(
                fn($s) => $s->getReadCount(),
                $execution->getStepExecutions()
            )),
        ]);
    }
);
```

See [Listeners & Events](events.md) for the full event catalogue.

PSR-6 / PSR-16 Cache
--------------------

Decorate the explorer with caching:

```php
use Lemric\BatchProcessing\Explorer\{CachedJobExplorer, SimpleCacheJobExplorer};

// PSR-6 (CacheItemPoolInterface)
$explorer = new CachedJobExplorer($simpleExplorer, $psr6Cache);

// PSR-16 (SimpleCache)
$explorer = new SimpleCacheJobExplorer($simpleExplorer, $psr16Cache);
```

Next Steps
----------

* [Listeners & Events (PSR-14)](events.md)
* [Configuration Reference](configuration.md)

