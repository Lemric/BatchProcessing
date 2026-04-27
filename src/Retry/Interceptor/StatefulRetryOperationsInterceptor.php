<?php

/**
 * This file is part of the Lemric package.
 * (c) Lemric
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
declare(strict_types=1);

namespace Lemric\BatchProcessing\Retry\Interceptor;

use Lemric\BatchProcessing\Retry\{RetryContextSupport, RetryOperations};
use Throwable;

/**
 * {@code StatefulRetryOperationsInterceptor} parity. Wraps a
 * {@see RetryOperations} so that retry state is persisted between physical invocations
 * (e.g. consecutive HTTP/CLI calls or worker job re-deliveries).
 *
 * Usage pattern:
 *   $result = $interceptor->execute('order-42', fn () => $service->process($order));
 *
 * On exception the state is persisted and the throwable is re-thrown immediately. The next
 * invocation reuses the state to honour the retry budget. On success the state is removed.
 */
final readonly class StatefulRetryOperationsInterceptor
{
    public function __construct(
        private RetryOperations $delegate,
        private RetryStateCacheInterface $stateCache,
    ) {
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $callback
     *
     * @return TResult
     */
    public function execute(string $key, callable $callback): mixed
    {
        $previous = $this->stateCache->get($key);
        // Note: the delegate RetryOperations sees a fresh attempt every time; the cached
        // counter is consulted to short-circuit when the budget has already been exhausted.
        if (null !== $previous && $previous->exhausted) {
            $this->stateCache->delete($key);
            throw new \Lemric\BatchProcessing\Exception\ExhaustedRetryException('Stateful retry budget already exhausted for key: '.$key);
        }

        try {
            $result = $this->delegate->execute(static fn () => $callback());
            $this->stateCache->delete($key);

            return $result;
        } catch (Throwable $e) {
            $context = new \Lemric\BatchProcessing\Retry\RetryContext();
            $previousCount = null !== $previous ? $previous->retryCount : 0;
            for ($i = 0; $i <= $previousCount; ++$i) {
                $context->registerThrowable($e);
            }
            $this->stateCache->put($key, RetryContextSupport::fromContext($context));
            throw $e;
        }
    }
}
