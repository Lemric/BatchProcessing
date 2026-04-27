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

use Lemric\BatchProcessing\Listener\SkipListenerInterface;
use Throwable;

abstract class SkipListenerSupport implements SkipListenerInterface
{
    public function onSkipInProcess(mixed $item, Throwable $t): void
    {
    }

    public function onSkipInRead(Throwable $t): void
    {
    }

    public function onSkipInWrite(mixed $item, Throwable $t): void
    {
    }
}
