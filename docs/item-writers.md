Item Writers
============

An **ItemWriter** writes a chunk of processed items to a destination.

ItemWriterInterface
-------------------

```php
namespace Lemric\BatchProcessing\Item;

use Lemric\BatchProcessing\Chunk\Chunk;

interface ItemWriterInterface
{
    public function write(Chunk $items): void;
}
```

The writer receives the entire chunk and should write all items in a single
operation for optimal performance.

Built-in Writers
----------------

### PdoItemWriter

Per-item prepared-statement writer:

```php
use Lemric\BatchProcessing\Item\Writer\PdoItemWriter;

$writer = new PdoItemWriter(
    pdo: $pdo,
    sql: 'INSERT INTO orders (id, total, status) VALUES (:id, :total, :status)
          ON DUPLICATE KEY UPDATE total = VALUES(total)',
    itemToParams: fn(Order $o): array => [
        'id'     => $o->id,
        'total'  => $o->total,
        'status' => $o->status->value,
    ],
);
```

### PdoBatchItemWriter

Optimized batch writer that executes the same prepared statement for every item
in the chunk and (optionally) asserts that each row affected exactly one update:

```php
use Lemric\BatchProcessing\Item\Writer\PdoBatchItemWriter;

$writer = new PdoBatchItemWriter(
    pdo: $pdo,
    sql: 'INSERT INTO orders (id, total, status) VALUES (:id, :total, :status)',
    columnNames: ['id', 'total', 'status'], // optional, used to bind associative items
    assertUpdates: true,
);
```

### CallbackItemWriter

Invokes a callable with the chunk's **output items** array:

```php
use Lemric\BatchProcessing\Item\Writer\CallbackItemWriter;

$writer = new CallbackItemWriter(function (array $items): void {
    foreach ($items as $item) {
        $this->api->send($item);
    }
});
```

### CompositeItemWriter

Delegates writing to multiple writers in sequence:

```php
use Lemric\BatchProcessing\Item\Writer\CompositeItemWriter;

$writer = new CompositeItemWriter([
    $databaseWriter,
    $auditLogWriter,
    $metricsWriter,
]);
```

### ClassifierCompositeItemWriter

Routes items to different writers based on a classifier callable. The classifier
receives the item and must return an `ItemWriterInterface`:

```php
use Lemric\BatchProcessing\Item\Writer\ClassifierCompositeItemWriter;

$writer = new ClassifierCompositeItemWriter(
    classifier: fn(mixed $item) => $item instanceof HighPriorityOrder
        ? $priorityWriter
        : $standardWriter,
);
```

### FlatFileItemWriter

Writes items to a flat file. Requires a `LineAggregatorInterface` from the
`Lemric\BatchProcessing\Item\FlatFile` namespace (e.g. `DelimitedLineAggregator`,
`FormatterLineAggregator`, `PassThroughLineAggregator`):

```php
use Lemric\BatchProcessing\Item\Writer\FlatFileItemWriter;
use Lemric\BatchProcessing\Item\FlatFile\DelimitedLineAggregator;

$writer = new FlatFileItemWriter(
    filePath: '/var/data/export.csv',
    lineAggregator: new DelimitedLineAggregator(delimiter: ','),
    headerCallback: fn() => 'id,total,status',
    footerCallback: null,
    append: false,
    encoding: 'UTF-8',
    lineSeparator: "\n",
);
```

### JsonFileItemWriter

Writes items to a JSON file:

```php
use Lemric\BatchProcessing\Item\Writer\JsonFileItemWriter;

$writer = new JsonFileItemWriter(
    filePath: '/var/data/export.json',
    headerCallback: null,
    footerCallback: null,
    jsonFlags: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
);
```

### RedisItemWriter

Writes items to a Redis data structure:

```php
use Lemric\BatchProcessing\Item\Writer\RedisItemWriter;
use Lemric\BatchProcessing\Item\Reader\RedisDataStructure;

$writer = new RedisItemWriter(
    client: $redis,
    key: 'processed:orders',
    structure: RedisDataStructure::LIST,
);
```

### MultiResourceItemWriter

Wraps a writer/stream delegate and rotates the resource (file, etc.) once a
limit is reached. The delegate must implement both `ItemWriterInterface` and
`ItemStreamInterface`:

```php
use Lemric\BatchProcessing\Item\Writer\MultiResourceItemWriter;

$writer = new MultiResourceItemWriter(
    delegate: $flatFileWriter,
    resourceSuffixCreator: fn(int $index): string => sprintf('_%03d.csv', $index),
    itemCountLimitPerResource: 10000,
);
```

### AsyncItemWriter

Resolves Fiber-wrapped items produced by `AsyncItemProcessor` and forwards them
to the underlying writer:

```php
use Lemric\BatchProcessing\Item\Writer\AsyncItemWriter;

$writer = new AsyncItemWriter($delegateWriter);
```

### ListItemWriter

Collects items into an in-memory list (useful for debugging/testing):

```php
use Lemric\BatchProcessing\Item\Writer\ListItemWriter;

$writer = new ListItemWriter();
// after execution:
$writer->getWrittenItems();
$writer->clear();
```

### ItemWriterAdapter

Adapts a method on an arbitrary object into an `ItemWriterInterface`. The method
is invoked with the `Chunk`:

```php
use Lemric\BatchProcessing\Item\Writer\ItemWriterAdapter;

$writer = new ItemWriterAdapter(
    targetObject: $myService,
    targetMethod: 'storeBatch',
);
```

Custom Writers
--------------

```php
use Lemric\BatchProcessing\Item\{ItemWriterInterface, ItemStreamInterface};
use Lemric\BatchProcessing\Chunk\Chunk;

final class ElasticsearchItemWriter implements ItemWriterInterface, ItemStreamInterface
{
    public function write(Chunk $items): void
    {
        $bulk = [];
        foreach ($items->getOutputItems() as $item) {
            $bulk[] = ['index' => ['_id' => $item->id]];
            $bulk[] = $item->toArray();
        }
        $this->client->bulk(['body' => $bulk]);
    }

    public function open(ExecutionContext $ctx): void {}
    public function update(ExecutionContext $ctx): void {}
    public function close(): void {}
}
```

Next Steps
----------

* [Chunk-Oriented Processing](chunk-processing.md)
* [Retry Framework](retry.md)

