Item Readers
============

An **ItemReader** is a strategy for reading items one-by-one from a data source.
Returns `null` when the data is exhausted.

ItemReaderInterface
-------------------

```php
namespace Lemric\BatchProcessing\Item;

interface ItemReaderInterface
{
    /**
     * @return mixed|null  null signals end-of-data
     */
    public function read(): mixed;
}
```

ItemStreamInterface
-------------------

Readers managing external resources (files, cursors, connections) should also
implement `ItemStreamInterface` for restart support:

```php
interface ItemStreamInterface
{
    public function open(ExecutionContext $executionContext): void;
    public function update(ExecutionContext $executionContext): void;
    public function close(): void;
}
```

Built-in Readers
----------------

### IteratorItemReader

Reads from any `iterable` (array, generator, iterator):

```php
use Lemric\BatchProcessing\Item\Reader\IteratorItemReader;

$reader = new IteratorItemReader(range(1, 10000));
$reader = new IteratorItemReader($myGenerator());
```

### ListItemReader

Reads from an in-memory array (consumes items in order):

```php
use Lemric\BatchProcessing\Item\Reader\ListItemReader;

$reader = new ListItemReader([
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
]);
```

### CallbackItemReader

Reads items by invoking a callback. Return `null` to signal end-of-data:

```php
use Lemric\BatchProcessing\Item\Reader\CallbackItemReader;

$reader = new CallbackItemReader(function () use (&$cursor): ?array {
    return $cursor->fetch() ?: null;
});
```

### ScriptItemReader

Adapts a `callable` into an `ItemReaderInterface` (the callable is invoked
per-item and should return `null` at end-of-data):

```php
use Lemric\BatchProcessing\Item\Reader\ScriptItemReader;

$reader = new ScriptItemReader(fn() => $service->next());
```

### ItemReaderAdapter

Adapts a method on an arbitrary object into an `ItemReaderInterface`. See the
class documentation for the exact signature.

### PdoItemReader

Cursor-based SQL reader for large datasets. Uses unbuffered queries:

```php
use Lemric\BatchProcessing\Item\Reader\PdoItemReader;

$reader = new PdoItemReader(
    pdo: $pdo,
    sql: 'SELECT * FROM orders WHERE status = :status ORDER BY id ASC',
    parameters: ['status' => 'pending'],
    fetchMode: \PDO::FETCH_ASSOC,
    rowMapper: fn(array $row): Order => Order::fromRow($row), // optional callable
    saveState: true,
);
```

### PaginatedPdoItemReader / PdoPagingItemReader

Page-based SQL readers:

```php
use Lemric\BatchProcessing\Item\Reader\PaginatedPdoItemReader;

$reader = new PaginatedPdoItemReader(
    pdo: $pdo,
    sql: 'SELECT * FROM orders ORDER BY id LIMIT :limit OFFSET :offset',
    parameters: [],
    pageSize: 1000,
);
```

`PdoPagingItemReader` provides an alternative paging strategy using
`Lemric\BatchProcessing\Item\Reader\Paging\*` components.

### CsvItemReader

Streaming CSV reader. **A `CsvFieldSetMapperInterface` is required** to map the
raw row to a domain object:

```php
use Lemric\BatchProcessing\Item\Reader\{CsvItemReader, CsvFieldSetMapperInterface};

$mapper = new class implements CsvFieldSetMapperInterface {
    public function mapFieldSet(array $fields): Order {
        return new Order(
            id: (int) $fields[0],
            name: $fields[1],
            total: (float) $fields[2],
        );
    }
};

$reader = new CsvItemReader(
    filePath: '/var/data/orders.csv',
    fieldSetMapper: $mapper,
    delimiter: ',',
    enclosure: '"',
    escape: '\\',
    linesToSkip: 1,
    strict: true,
    saveState: true,
);
```

### JsonItemReader

Reads items from a JSON file (expects an array at the root):

```php
use Lemric\BatchProcessing\Item\Reader\JsonItemReader;

$reader = new JsonItemReader(
    filePath: '/var/data/orders.json',
    mapper: fn(array $row): Order => Order::fromArray($row), // optional callable
);
```

### JsonLinesItemReader

Reads newline-delimited JSON (one object per line):

```php
use Lemric\BatchProcessing\Item\Reader\JsonLinesItemReader;

$reader = new JsonLinesItemReader('/var/data/orders.jsonl');
```

### RedisItemReader

Reads items from a Redis data structure (`LIST`, `SET`, `STREAM`, …):

```php
use Lemric\BatchProcessing\Item\Reader\{RedisItemReader, RedisDataStructure};

$reader = new RedisItemReader(
    client: $redis,
    key: 'queue:orders',
    structure: RedisDataStructure::LIST,
);
```

### MultiResourceItemReader

Wraps another reader/stream and switches between resources transparently. The
delegate must implement both `ItemReaderInterface` and `ItemStreamInterface`:

```php
use Lemric\BatchProcessing\Item\Reader\MultiResourceItemReader;

$reader = new MultiResourceItemReader($delegate);
```

### TransformingItemReader

Decorator that transforms each item read:

```php
use Lemric\BatchProcessing\Item\Reader\TransformingItemReader;

$reader = new TransformingItemReader(
    delegate: $csvReader,
    transformer: fn(array $row): Order => Order::fromArray($row),
);
```

### SynchronizedItemStreamReader

Synchronization wrapper around an `ItemReaderInterface & ItemStreamInterface`
delegate, intended for partitioned/multi-thread scenarios.

### AbstractItemReader

Base class for custom readers, providing `name`/`saveState`/checkpoint plumbing.

Custom Readers
--------------

```php
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemStreamInterface};
use Lemric\BatchProcessing\Domain\ExecutionContext;

final class ApiItemReader implements ItemReaderInterface, ItemStreamInterface
{
    private int $page = 0;
    /** @var list<array> */
    private array $buffer = [];

    public function open(ExecutionContext $ctx): void
    {
        $this->page = $ctx->getInt('api.page', 0);
    }

    public function read(): mixed
    {
        if ([] === $this->buffer) {
            $this->buffer = $this->fetchPage(++$this->page);
        }
        return array_shift($this->buffer);
    }

    public function update(ExecutionContext $ctx): void
    {
        $ctx->put('api.page', $this->page);
    }

    public function close(): void {}
}
```

Next Steps
----------

* [Item Processors](item-processors.md)
* [Item Writers](item-writers.md)

