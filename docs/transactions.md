Transactions
============

The `TransactionManagerInterface` abstracts transaction boundaries around
chunk writes.

TransactionManagerInterface
---------------------------

```php
namespace Lemric\BatchProcessing\Transaction;

interface TransactionManagerInterface
{
    public function begin(): void;
    public function commit(): void;
    public function rollback(): void;
}
```

Implementations
---------------

### PdoTransactionManager

Wraps PDO native transactions:

```php
use Lemric\BatchProcessing\Transaction\PdoTransactionManager;

$txManager = new PdoTransactionManager($pdo);
```

### ResourcelessTransactionManager

A no-op implementation for environments without a database (in-memory
processing, tests):

```php
use Lemric\BatchProcessing\Transaction\ResourcelessTransactionManager;

$txManager = new ResourcelessTransactionManager();
```

Transaction Flow in Chunk Processing
--------------------------------------

```
txManager->begin()
    writer->write(chunk)
txManager->commit()           ← success

txManager->begin()
    writer->write(chunk)
txManager->rollback()         ← failure → scan mode
```

On failure, the framework enters **scan mode**: each item is written in its
own transaction to isolate the failing one.

Next Steps
----------

* [Repository & Schema](repository.md)
* [Chunk-Oriented Processing](chunk-processing.md)

