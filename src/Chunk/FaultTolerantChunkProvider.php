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

namespace Lemric\BatchProcessing\Chunk;

use Lemric\BatchProcessing\Item\ItemReaderInterface;
use Lemric\BatchProcessing\Listener\CompositeListener;
use Lemric\BatchProcessing\Retry\{RetryOperations, RetryTemplate};
use Lemric\BatchProcessing\Skip\{NeverSkipItemSkipPolicy, SkipPolicyInterface};
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Skip/retry-aware {@see SimpleChunkProvider}. Honours the configured {@see RetryOperations}
 * and {@see SkipPolicyInterface} on read failures.
 *
 * The {@code $readerTransactionalQueue} flag: when {@code true} the
 * provider must NOT buffer items for later rescan because the reader itself participates in
 * the transaction (e.g. JMS / Redis stream). When {@code false} (default) successfully read
 * items can be remembered for skip-on-write rescans by downstream processors.
 *
 * @template TIn
 *
 * @extends SimpleChunkProvider<TIn>
 */
final class FaultTolerantChunkProvider extends SimpleChunkProvider
{
    /**
     * @param ItemReaderInterface<TIn> $reader
     */
    public function __construct(
        ItemReaderInterface $reader,
        int $chunkSize,
        CompositeListener $listeners = new CompositeListener(),
        ?LoggerInterface $logger = null,
        ?CompletionPolicyInterface $completionPolicy = null,
        private readonly RetryOperations $retryOperations = new RetryTemplate(),
        private readonly SkipPolicyInterface $skipPolicy = new NeverSkipItemSkipPolicy(),
        private readonly bool $readerTransactionalQueue = false,
    ) {
        parent::__construct($reader, $chunkSize, $listeners, $logger, $completionPolicy);
    }

    public function isReaderTransactionalQueue(): bool
    {
        return $this->readerTransactionalQueue;
    }

    protected function doRead(): mixed
    {
        return $this->doReadWithSkip(0);
    }

    private function doReadWithSkip(int $skipsSoFar): mixed
    {
        try {
            $this->listeners->beforeRead();
            $item = $this->retryOperations->execute(fn () => $this->reader->read());
            if (null !== $item) {
                $this->listeners->afterRead($item);
            }

            return $item;
        } catch (Throwable $e) {
            $this->listeners->onReadError($e);
            if ($this->skipPolicy->shouldSkip($e, $skipsSoFar)) {
                $this->listeners->onSkipInRead($e);
                $this->logger->warning('Skipping unreadable item: '.$e->getMessage());

                return $this->doReadWithSkip($skipsSoFar + 1);
            }
            throw $e;
        }
    }
}
