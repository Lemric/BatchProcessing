Skip Framework
==============

The skip framework determines whether a failed item should be skipped (logged
and ignored) or whether the exception should propagate and fail the step.

SkipPolicyInterface
-------------------

```php
namespace Lemric\BatchProcessing\Skip;

interface SkipPolicyInterface
{
    /**
     * @return bool true = skip this item, false = propagate exception
     *
     * @throws SkipLimitExceededException when the skip limit is exceeded
     */
    public function shouldSkip(\Throwable $t, int $skipCount): bool;
}
```

Built-in Skip Policies
----------------------

### LimitCheckingItemSkipPolicy

The most commonly used policy. Skips items matching configured exception
types, up to a maximum limit. `Lemric\BatchProcessing\Exception\SkippableException`
is always skippable up to the limit.

```php
use Lemric\BatchProcessing\Skip\LimitCheckingItemSkipPolicy;

$policy = new LimitCheckingItemSkipPolicy(
    skipLimit: 100,
    skippableExceptions: [
        \InvalidArgumentException::class => true,
        \PDOException::class             => false, // explicitly non-skippable
    ],
);
```

### AlwaysSkipItemSkipPolicy

Skips all exceptions unconditionally (use with caution):

```php
use Lemric\BatchProcessing\Skip\AlwaysSkipItemSkipPolicy;

$policy = new AlwaysSkipItemSkipPolicy();
```

### NeverSkipItemSkipPolicy

Never skips — all exceptions propagate. This is the default.

### ExceptionClassifierSkipPolicy

Delegates to different skip policies based on the exception class:

```php
use Lemric\BatchProcessing\Skip\{ExceptionClassifierSkipPolicy, AlwaysSkipItemSkipPolicy, NeverSkipItemSkipPolicy};

$policy = new ExceptionClassifierSkipPolicy(
    policies: [
        \PDOException::class             => new NeverSkipItemSkipPolicy(),
        \InvalidArgumentException::class => new AlwaysSkipItemSkipPolicy(),
    ],
    defaultPolicy: new NeverSkipItemSkipPolicy(),
);
```

### ExceptionHierarchySkipPolicy

Walks the full exception class hierarchy (including parent classes) to decide
whether to skip.

### CompositeSkipPolicy

Combines multiple skip policies.

### CountingSkipPolicy

Tracks skip counts independently per exception type (used internally by
`SkipCounter`).

Using Skip in Steps
-------------------

```php
$step = $stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->faultTolerant()
    ->skip(\Lemric\BatchProcessing\Exception\SkippableException::class)
    ->skip(\InvalidArgumentException::class)
    ->skipLimit(200)
    ->build();
```

Or pass a fully-built policy:

```php
$step = $stepBuilderFactory->get('importStep')
    ->chunk(500, $reader, $processor, $writer)
    ->skipPolicy(new LimitCheckingItemSkipPolicy(50, [\InvalidArgumentException::class => true]))
    ->build();
```

Skip Listeners
--------------

`SkipListenerInterface` exposes one callback per phase:

```php
public function onSkipInRead(\Throwable $t): void;
public function onSkipInProcess(mixed $item, \Throwable $t): void;
public function onSkipInWrite(mixed $item, \Throwable $t): void;
```

Skip Counts on StepExecution
----------------------------

Skip counts are tracked independently per phase:

```php
$stepExecution->getReadSkipCount();
$stepExecution->getProcessSkipCount();
$stepExecution->getWriteSkipCount();
$stepExecution->getSkipCount(); // total
```

Next Steps
----------

* [Retry Framework](retry.md)
* [Exception Hierarchy](exceptions.md)

