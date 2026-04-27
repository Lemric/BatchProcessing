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

use Lemric\BatchProcessing\Listener\Support\ItemReadListenerSupport;
use Throwable;

final class ItemReadListenerAdapter extends ItemReadListenerSupport
{
    use DispatchesHooks;

    public function afterRead(mixed $item): void
    {
        $this->dispatch('afterRead', $item);
    }

    public function beforeRead(): void
    {
        $this->dispatch('beforeRead');
    }

    public function onReadError(Throwable $t): void
    {
        $this->dispatch('onReadError', $t);
    }
}
