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

namespace Lemric\BatchProcessing\Listener;

use Lemric\BatchProcessing\Chunk\Chunk;
use Throwable;

interface ItemWriteListenerInterface
{
    /**
     * @param Chunk<mixed, mixed> $items
     */
    public function afterWrite(Chunk $items): void;

    /**
     * @param Chunk<mixed, mixed> $items
     */
    public function beforeWrite(Chunk $items): void;

    /**
     * @param Chunk<mixed, mixed> $items
     */
    public function onWriteError(Throwable $t, Chunk $items): void;
}
