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

use Lemric\BatchProcessing\Listener\Support\ItemProcessListenerSupport;

final class ItemProcessListenerAdapter extends ItemProcessListenerSupport
{
    use DispatchesHooks;

    public function afterProcess(mixed $item, mixed $result): void
    {
        $this->dispatch('afterProcess', $item, $result);
    }

    public function beforeProcess(mixed $item): void
    {
        $this->dispatch('beforeProcess', $item);
    }
}
