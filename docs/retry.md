Retry Framework
===============

The retry framework provides a standalone, configurable mechanism for retrying
failed operations. It is used internally by chunk processing but can also be
used independently.

RetryTemplate
-------------

`RetryTemplate` is the main entry point. It executes a callback with automatic
retry according to a configured policy and back-off strategy:

```php
use Lemric\BatchProcessing\Retry\RetryTemplate;
use Lemric\BatchProcessing\Retry\Policy\SimpleRetryPolicy;
use Lemric\BatchProcessing\Retry\Backoff\ExponentialBackOffPolicy;

$retry = new RetryTemplate(
    retryPolicy:   new SimpleRetryPolicy(maxAttempts: 3),
    backOffPolicy: new ExponentialBackOffPolicy(initial: 200, multiplier: 2.0, max: 5000),
);

$result = $retry->execute(
    callback: fn() => $this->httpClient->request('GET', $url),
    recoveryCallback: fn(\Throwable $lastException, int $retryCount) => $this->fallbackValue,
);
```

Defaults: `RetryPolicy = new SimpleRetryPolicy()` (3 attempts, retries
`RuntimeException`); `BackOffPolicy = new NoBackOffPolicy()`.

The recovery callback receives `($lastException, $retryCount)` and is invoked
when retries are exhausted; if it is not provided, the last exception is
re-thrown. Recovery callbacks may also implement `RecoveryCallbackInterface`.

Retry Policies
--------------

| Policy                                    | Description                                     |
|-------------------------------------------|-------------------------------------------------|
| `SimpleRetryPolicy`                       | Retry up to N times for given exception types   |
| `MaxAttemptsRetryPolicy`                  | Plain max-attempts counter                      |
| `NeverRetryPolicy`                        | Never retry (single attempt)                    |
| `AlwaysRetryPolicy`                       | Always retry (use with back-off!)               |
| `TimeoutRetryPolicy`                      | Retry until a time limit is reached             |
| `CircuitBreakerRetryPolicy`               | Circuit-breaker around a delegate policy        |
| `ExceptionClassifierRetryPolicy`          | Different policies per exception type           |
| `BinaryExceptionClassifierRetryPolicy`    | Binary retry/no-retry classification            |
| `CompositeRetryPolicy`                    | Logical combination of multiple policies        |

### SimpleRetryPolicy

```php
use Lemric\BatchProcessing\Retry\Policy\SimpleRetryPolicy;

$policy = new SimpleRetryPolicy(
    maxAttempts: 5,
    retryableExceptions: [
        \PDOException::class    => true,
        \RuntimeException::class => true,
    ],
);
```

### CircuitBreakerRetryPolicy

```php
use Lemric\BatchProcessing\Retry\Policy\CircuitBreakerRetryPolicy;

$policy = new CircuitBreakerRetryPolicy(
    delegate: new SimpleRetryPolicy(maxAttempts: 3),
    openTimeout: 5_000,    // ms before attempting to close
    resetTimeout: 20_000,  // ms before resetting counters
);
```

### CompositeRetryPolicy

```php
use Lemric\BatchProcessing\Retry\Policy\CompositeRetryPolicy;

$policy = new CompositeRetryPolicy(
    policies: [$maxAttemptsPolicy, $timeoutPolicy],
    optimistic: true, // OR; false = AND
);
```

Back-Off Policies
-----------------

| Policy                            | Algorithm                                        | Default parameters                                |
|-----------------------------------|--------------------------------------------------|--------------------------------------------------|
| `NoBackOffPolicy`                 | No delay between retries                         | —                                                |
| `FixedBackOffPolicy`              | Fixed delay                                      | `period` (ms)                                    |
| `ExponentialBackOffPolicy`        | `initial × multiplier^attempt`                   | `initial=100`, `multiplier=2.0`, `max=30_000`    |
| `ExponentialRandomBackOffPolicy`  | Exponential + jitter (avoids thundering herd)     | Same as exponential + random factor              |
| `UniformRandomBackOffPolicy`      | Random delay in `[min, max]`                     | `min`, `max` (ms)                                |

```php
use Lemric\BatchProcessing\Retry\Backoff\ExponentialBackOffPolicy;

$backoff = new ExponentialBackOffPolicy(
    initial:    100,    // 100ms initial delay
    multiplier: 2.0,    // doubles each time (must be > 1.0)
    max:        30_000, // cap at 30s
);
```

Using Retry in Steps
--------------------

```php
$step = $stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->faultTolerant()
    ->retry(\PDOException::class, maxAttempts: 3)
    ->retry(\RuntimeException::class, maxAttempts: 5)
    ->backOff(new ExponentialBackOffPolicy(initial: 200, multiplier: 2.0, max: 5000))
    ->build();
```

Or pass a fully-built policy:

```php
$step = $stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->retryPolicy(new CircuitBreakerRetryPolicy(new SimpleRetryPolicy(3)))
    ->build();
```

Retry Synchronization
---------------------

`RetrySynchronizationManager` exposes the active `RetryContext` to nested
operations (useful when the callback is several layers deep).

Retry Listeners
---------------

`RetryListenerInterface` lets you observe retry attempts. See
[Listeners & Events](events.md) for the exact method signatures (`open()`,
`close()`, `onError()`).

Next Steps
----------

* [Skip Framework](skip.md)
* [Chunk-Oriented Processing](chunk-processing.md)

