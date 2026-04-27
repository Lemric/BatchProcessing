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

namespace Lemric\BatchProcessing\Listener\Logging;

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Listener\ChunkListenerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class LoggingChunkListener implements ChunkListenerInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function afterChunk(ChunkContext $context): void
    {
        $this->logger->debug('Chunk completed');
    }

    public function afterChunkError(ChunkContext $context, Throwable $t): void
    {
        $this->logger->warning('Chunk error', ['exception' => $t->getMessage()]);
    }

    public function beforeChunk(ChunkContext $context): void
    {
        $this->logger->debug('Chunk starting');
    }
}
