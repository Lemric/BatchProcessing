Item Processors
===============

An **ItemProcessor** transforms or filters a single item between reading and
writing.

ItemProcessorInterface
----------------------

```php
namespace Lemric\BatchProcessing\Item;

interface ItemProcessorInterface
{
    /**
     * @return mixed|null  null = item is filtered out (not sent to writer)
     */
    public function process(mixed $item): mixed;
}
```

Returning `null` filters the item out — it will not reach the writer and
increments the step's `filterCount`.

Built-in Processors
-------------------

### PassThroughItemProcessor

Returns the item unchanged. Used internally when no processor is configured:

```php
use Lemric\BatchProcessing\Item\Processor\PassThroughItemProcessor;

$processor = new PassThroughItemProcessor();
```

### FilteringItemProcessor

Filters items based on a predicate callable:

```php
use Lemric\BatchProcessing\Item\Processor\FilteringItemProcessor;

$processor = new FilteringItemProcessor(
    fn(int $item): bool => $item % 2 === 0
);
```

### CompositeItemProcessor

Chains multiple processors — the output of each becomes the input of the next.
If any processor returns `null`, the item is filtered:

```php
use Lemric\BatchProcessing\Item\Processor\CompositeItemProcessor;

$processor = new CompositeItemProcessor([
    $validatingProcessor,
    $transformingProcessor,
    $enrichingProcessor,
]);
```

### ChainItemProcessor

Similar to `CompositeItemProcessor`, chains processors sequentially:

```php
use Lemric\BatchProcessing\Item\Processor\ChainItemProcessor;

$processor = new ChainItemProcessor([$p1, $p2, $p3]);
```

### ValidatingItemProcessor

Runs a validator callable; on failure it either throws or filters out the item:

```php
use Lemric\BatchProcessing\Item\Processor\ValidatingItemProcessor;

$processor = new ValidatingItemProcessor(
    validator: fn(mixed $item) => $myValidator->validate($item), // any callable
    exceptionClass: \InvalidArgumentException::class, // optional
    message: 'Validation failed for item.',
    filter: false,    // true = silently filter invalid items
);
```

### BeanValidatingItemProcessor

A simpler variant that delegates to any validator-callable returning a list of
violations:

```php
use Lemric\BatchProcessing\Item\Processor\BeanValidatingItemProcessor;

$processor = new BeanValidatingItemProcessor(
    validator: fn(object $item): iterable => $symfonyValidator->validate($item),
    filter: false,
);
```

### AsyncItemProcessor

Wraps another processor and runs it inside a PHP Fiber, returning a Fiber as
the output (resolved later by an async-aware writer):

```php
use Lemric\BatchProcessing\Item\Processor\AsyncItemProcessor;

$processor = new AsyncItemProcessor($delegateProcessor);
```

### ScriptItemProcessor

Adapts a `callable` into an `ItemProcessorInterface`:

```php
use Lemric\BatchProcessing\Item\Processor\ScriptItemProcessor;

$processor = new ScriptItemProcessor(
    fn(Order $order): Order => $order->withTax()
);
```

### ItemProcessorAdapter

Adapts a method on an arbitrary object into an `ItemProcessorInterface`:

```php
use Lemric\BatchProcessing\Item\Processor\ItemProcessorAdapter;

$processor = new ItemProcessorAdapter(
    targetObject: $myService,
    targetMethod: 'process',
);
```

Custom Processors
-----------------

```php
use Lemric\BatchProcessing\Item\ItemProcessorInterface;

final class OrderEnrichmentProcessor implements ItemProcessorInterface
{
    public function __construct(private readonly ExchangeRateService $rates) {}

    public function process(mixed $item): ?Order
    {
        if ($item->total <= 0) {
            return null; // filter out
        }

        return $item->withConvertedTotal(
            $this->rates->convert($item->total, $item->currency, 'EUR')
        );
    }
}
```

Next Steps
----------

* [Item Writers](item-writers.md)
* [Item Readers](item-readers.md)

