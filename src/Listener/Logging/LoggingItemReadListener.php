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

use Lemric\BatchProcessing\Listener\ItemReadListenerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class LoggingItemReadListener implements ItemReadListenerInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function afterRead(mixed $item): void
    {
        $this->logger->debug('Item read', ['item' => is_scalar($item) ? $item : get_debug_type($item)]);
    }

    public function beforeRead(): void
    {
        $this->logger->debug('About to read item');
    }

    public function onReadError(Throwable $t): void
    {
        $this->logger->warning('Read error', ['exception' => $t->getMessage()]);
    }
}
