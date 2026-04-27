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

namespace Lemric\BatchProcessing\Retry;

use Lemric\BatchProcessing\Exception\{ExhaustedRetryException, JobInterruptedException, RetryInterruptedException};
use Lemric\BatchProcessing\Listener\RetryListenerInterface;
use Lemric\BatchProcessing\Retry\Backoff\{BackOffPolicyInterface, NoBackOffPolicy};
use Lemric\BatchProcessing\Retry\Policy\SimpleRetryPolicy;
use Throwable;

/**
 * Reference {@see RetryOperations} implementation. Drives the configured {@see RetryPolicyInterface}
 * and {@see BackOffPolicyInterface}, propagating the last exception when retries are exhausted
 * (or invoking the optional recovery callback).
 */
final class RetryTemplate implements RetryOperations
{
    /** @var list<RetryListenerInterface> */
    private array $listeners = [];

    public function __construct(
        private readonly RetryPolicyInterface $retryPolicy = new SimpleRetryPolicy(),
        private readonly BackOffPolicyInterface $backOffPolicy = new NoBackOffPolicy(),
    ) {
    }

    public function execute(callable $callback, ?callable $recoveryCallback = null): mixed
    {
        $context = $this->retryPolicy->open();
        $lastException = null;

        // Register in synchronization manager for thread/fiber-local access.
        $previousContext = RetrySynchronizationManager::register($context);

        // Notify listeners — if any veto, skip retry entirely.
        foreach ($this->listeners as $listener) {
            if (!$listener->open($context)) {
                RetrySynchronizationManager::register($previousContext);
                $this->retryPolicy->close($context);
                throw new ExhaustedRetryException('Retry vetoed by listener.');
            }
        }

        try {
            while ($this->retryPolicy->canRetry($context)) {
                try {
                    $result = $callback($context);
                    $this->retryPolicy->registerThrowable($context, null);

                    return $result;
                } catch (JobInterruptedException $e) {
                    throw new RetryInterruptedException('Retry interrupted: '.$e->getMessage(), previous: $e);
                } catch (Throwable $e) {
                    $lastException = $e;
                    $this->retryPolicy->registerThrowable($context, $e);

                    foreach ($this->listeners as $listener) {
                        $listener->onError($context, $e);
                    }

                    if (!$this->retryPolicy->canRetry($context)) {
                        break;
                    }
                    $this->backOffPolicy->backOff();
                }
            }
        } finally {
            foreach ($this->listeners as $listener) {
                $listener->close($context);
            }
            $this->retryPolicy->close($context);
            RetrySynchronizationManager::register($previousContext);
        }

        $context->setExhausted();

        // Support RecoveryCallbackInterface as well as plain callables
        if (null !== $recoveryCallback && null !== $lastException) {
            if ($recoveryCallback instanceof RecoveryCallbackInterface) {
                return $recoveryCallback->recover($context);
            }

            return $recoveryCallback($lastException, $context->getRetryCount());
        }

        if (null !== $lastException) {
            throw $lastException;
        }

        throw new ExhaustedRetryException('Retry policy refused initial attempt.');
    }

    public function registerListener(RetryListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }
}
