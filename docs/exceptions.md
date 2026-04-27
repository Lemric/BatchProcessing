Exception Hierarchy
===================

All domain exceptions extend `Lemric\BatchProcessing\Exception\BatchException`,
which itself extends PHP's `\RuntimeException`.

Hierarchy
---------

```
\RuntimeException
└── BatchException
    ├── JobExecutionException
    │   ├── DuplicateJobException
    │   ├── JobExecutionAlreadyRunningException
    │   ├── JobExecutionNotRunningException
    │   ├── JobExecutionNotStoppedException
    │   ├── JobInstanceAlreadyCompleteException
    │   ├── JobParametersInvalidException
    │   ├── JobRestartException
    │   ├── NoSuchJobException
    │   ├── NoSuchJobExecutionException
    │   └── NoSuchJobInstanceException
    │
    ├── StepExecutionException
    │   ├── JobInterruptedException
    │   ├── StartLimitExceededException
    │   └── UnexpectedStepExecutionException
    │
    ├── ItemReaderException
    │   ├── NonTransientResourceException
    │   ├── ParseException
    │   └── UnexpectedInputException
    │
    ├── ItemWriterException
    │   └── WriteFailedException
    │
    ├── RetryException
    │   ├── ExhaustedRetryException
    │   ├── RetryInterruptedException
    │   └── RetryPolicyViolationException
    │
    ├── RepositoryException
    │   └── OptimisticLockingFailureException
    │
    ├── FlowExecutionException
    ├── TransactionException
    ├── ScopeNotActiveException
    ├── SkipLimitExceededException
    ├── SkippableException                  // marker base for skippable errors
    ├── NonTransientException               // marker: should NOT be retried
    ├── TransientException                  // marker: CAN be retried
    └── UnexpectedJobExecutionException
```

Usage Guidelines
----------------

| Exception Type          | When to throw                              | Retry? | Skip? |
|-------------------------|--------------------------------------------|--------|-------|
| `TransientException`    | Temporary failure (network, timeout)       | ✅     | maybe |
| `NonTransientException` | Permanent failure (bad data, logic error)  | ❌     | maybe |
| `SkippableException`    | Item can be safely ignored                 | —      | ✅    |
| `ParseException`        | Malformed CSV/JSON data                    | ❌     | ✅    |
| `WriteFailedException`  | Database write failure                     | ✅     | maybe |

Creating Custom Exceptions
---------------------------

```php
use Lemric\BatchProcessing\Exception\SkippableException;

final class InvalidOrderException extends SkippableException
{
    public function __construct(string $orderId)
    {
        parent::__construct("Invalid order: {$orderId}");
    }
}
```

