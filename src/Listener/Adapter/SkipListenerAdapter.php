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

namespace Lemric\BatchProcessing\Listener\Adapter;

use Lemric\BatchProcessing\Listener\Support\SkipListenerSupport;
use Throwable;

final class SkipListenerAdapter extends SkipListenerSupport
{
    use DispatchesHooks;

    public function onSkipInProcess(mixed $item, Throwable $t): void
    {
        $this->dispatch('onSkipInProcess', $item, $t);
    }

    public function onSkipInRead(Throwable $t): void
    {
        $this->dispatch('onSkipInRead', $t);
    }

    public function onSkipInWrite(mixed $item, Throwable $t): void
    {
        $this->dispatch('onSkipInWrite', $item, $t);
    }
}
