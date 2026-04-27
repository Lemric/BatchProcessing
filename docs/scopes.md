Scopes & Late Binding
=====================

Scopes allow late-binding of values to step/job-scoped components at execution
time.

Scope Classes
-------------

| Class                   | Namespace                            |
|-------------------------|--------------------------------------|
| `AbstractScope`         | `Lemric\BatchProcessing\Scope`        |
| `JobScope`              | `Lemric\BatchProcessing\Scope`        |
| `StepScope`             | `Lemric\BatchProcessing\Scope`        |

Scope Containers
----------------

A scoped container resolves and caches scoped beans for the lifetime of a
single execution:

| Class                       | Namespace                                |
|-----------------------------|------------------------------------------|
| `ScopedContainerInterface`  | `Lemric\BatchProcessing\Scope\Container` |
| `InMemoryScopedContainer`   | `Lemric\BatchProcessing\Scope\Container` |

Expression Resolution
---------------------

Late-binding expressions reference job parameters or execution context values:

| Class                                          | Namespace                                 |
|------------------------------------------------|-------------------------------------------|
| `LateBindingExpressionResolverInterface`       | `Lemric\BatchProcessing\Scope\Expression` |
| `SimpleLateBindingExpressionResolver`          | `Lemric\BatchProcessing\Scope\Expression` |

The simple resolver supports placeholders that reference parameters / context
values; refer to the class for the exact syntax supported in your version.

Attributes
----------

| Attribute                                            | Purpose                          |
|------------------------------------------------------|----------------------------------|
| `Lemric\BatchProcessing\Attribute\JobScope`          | Marks a class as `JobScope`      |
| `Lemric\BatchProcessing\Attribute\StepScope`         | Marks a class as `StepScope`     |
| `Lemric\BatchProcessing\Attribute\Listener\*`        | Listener attribute markers       |

```php
use Lemric\BatchProcessing\Attribute\StepScope;

#[StepScope]
final class PartitionAwareReader implements ItemReaderInterface
{
    public function __construct(
        private readonly int $minValue,
        private readonly int $maxValue,
    ) {}

    public function read(): mixed { /* ... */ }
}
```

Scope Lifecycle
---------------

1. **Activation** — when a Job/Step starts, its scope is activated and a fresh
   `ScopedContainer` is bound.
2. **Resolution** — scoped beans are resolved (or rebuilt) on first access
   from inside the active scope.
3. **Deactivation** — when the Job/Step ends, the scope is deactivated and
   resolved beans are released.

Accessing a scoped bean outside its active scope throws
`Lemric\BatchProcessing\Exception\ScopeNotActiveException`.

Reset Listener
--------------

`Lemric\BatchProcessing\Listener\ScopeResetListener` clears the scoped
container between runs to avoid leaking state across executions.

Next Steps
----------

* [Symfony Bridge](integration/symfony.md)
* [Partition & Parallel Processing](partition.md)

