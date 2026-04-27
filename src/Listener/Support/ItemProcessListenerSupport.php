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

namespace Lemric\BatchProcessing\Listener\Support;

use Lemric\BatchProcessing\Listener\ItemProcessListenerInterface;
use Throwable;

abstract class ItemProcessListenerSupport implements ItemProcessListenerInterface
{
    public function afterProcess(mixed $item, mixed $result): void
    {
    }

    public function beforeProcess(mixed $item): void
    {
    }

    public function onProcessError(mixed $item, Throwable $t): void
    {
    }
}
