Tasklet Steps
=============

A **Tasklet** encapsulates arbitrary logic that does not follow the
read-process-write chunk model. Common use cases: cleanup, file operations,
DDL statements, notifications.

TaskletInterface
----------------

```php
namespace Lemric\BatchProcessing\Step;

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\StepContribution;

interface TaskletInterface
{
    public function execute(
        StepContribution $contribution,
        ChunkContext $chunkContext,
    ): RepeatStatus;
}
```

### RepeatStatus

| Value         | Meaning                                              |
|---------------|------------------------------------------------------|
| `FINISHED`    | Step execution is complete.                          |
| `CONTINUABLE` | The framework will call `execute()` again.           |

Example: Cleanup Tasklet
-------------------------

```php
use Lemric\BatchProcessing\Step\{TaskletInterface, RepeatStatus};
use Lemric\BatchProcessing\Domain\StepContribution;
use Lemric\BatchProcessing\Chunk\ChunkContext;

final class PurgeOldRecordsTasklet implements TaskletInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly int $daysToKeep = 90,
    ) {}

    public function execute(
        StepContribution $contribution,
        ChunkContext $chunkContext,
    ): RepeatStatus {
        $stmt = $this->pdo->prepare(
            'DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $stmt->execute(['days' => $this->daysToKeep]);
        $contribution->incrementWriteCount($stmt->rowCount());

        return RepeatStatus::FINISHED;
    }
}
```

Building a Tasklet Step
-----------------------

```php
$step = $ctx['stepBuilderFactory']->get('cleanupStep')
    ->tasklet(new PurgeOldRecordsTasklet($pdo, daysToKeep: 90))
    ->build();
```

SystemCommandTasklet
--------------------

A built-in tasklet for executing shell commands. Inspect the class for the
exact constructor parameters available in your version (timeout, working
directory, environment, signal handling when `ext-pcntl` is loaded, etc.).

```php
use Lemric\BatchProcessing\Step\SystemCommandTasklet;

$step = $ctx['stepBuilderFactory']->get('compressStep')
    ->tasklet(new SystemCommandTasklet('gzip /var/data/export.csv'))
    ->build();
```

Continuable Tasklets
--------------------

Return `RepeatStatus::CONTINUABLE` to be invoked again. Useful for paginated
cleanup:

```php
public function execute(
    StepContribution $contribution,
    ChunkContext $chunkContext,
): RepeatStatus {
    $deleted = $this->deleteNextBatch(limit: 1000);
    $contribution->incrementWriteCount($deleted);

    return $deleted > 0 ? RepeatStatus::CONTINUABLE : RepeatStatus::FINISHED;
}
```

Next Steps
----------

* [Steps](steps.md)
* [Jobs](jobs.md)

