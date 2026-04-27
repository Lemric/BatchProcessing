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

use Lemric\BatchProcessing\Domain\{StepContribution, StepExecution};
use Lemric\BatchProcessing\Item\ItemReaderInterface;
use Lemric\BatchProcessing\Listener\CompositeListener;
use Psr\Log\{LoggerInterface, NullLogger};

/**
 * Reference {@see ChunkProviderInterface} that delegates to a single {@see ItemReaderInterface}.
 * Reads up to {@code $chunkSize} items per call, dispatching beforeRead/afterRead listener hooks.
 *
 * Skip-on-read is intentionally not handled here: when used inside the
 * {@see \Lemric\BatchProcessing\Step\ChunkOrientedStep}, the step still owns retry/skip
 * semantics. Use {@see FaultTolerantChunkProvider} when you need read-skip support.
 *
 * @template TIn
 *
 * @implements ChunkProviderInterface<TIn>
 */
class SimpleChunkProvider implements ChunkProviderInterface
{
    protected LoggerInterface $logger;

    /**
     * @param ItemReaderInterface<TIn> $reader
     */
    public function __construct(
        protected readonly ItemReaderInterface $reader,
        protected readonly int $chunkSize,
        protected readonly CompositeListener $listeners = new CompositeListener(),
        ?LoggerInterface $logger = null,
        protected readonly ?CompletionPolicyInterface $completionPolicy = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function provide(StepExecution $stepExecution, StepContribution $contribution): Chunk
    {
        /** @var list<TIn> $inputs */
        $inputs = [];
        $policy = $this->completionPolicy;
        $chunkContext = new ChunkContext($contribution);
        $policy?->start($chunkContext);

        $i = 0;
        while (true) {
            if (null !== $policy) {
                if ($policy->isComplete($chunkContext)) {
                    break;
                }
            } elseif ($i >= $this->chunkSize) {
                break;
            }

            $item = $this->doRead();
            if (null === $item) {
                break;
            }

            $contribution->incrementReadCount();
            $inputs[] = $item;
            ++$i;

            $policy?->update($chunkContext);
        }

        /** @var Chunk<TIn, mixed> $chunk */
        $chunk = new Chunk($inputs, []);

        return $chunk;
    }

    /**
     * @return TIn|null
     */
    protected function doRead(): mixed
    {
        $this->listeners->beforeRead();
        $item = $this->reader->read();
        if (null !== $item) {
            $this->listeners->afterRead($item);
        }

        return $item;
    }
}
