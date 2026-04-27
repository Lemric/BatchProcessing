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

use Closure;

/**
 * Reusable dispatch logic shared by every hook adapter.
 */
trait DispatchesHooks
{
    /** @var array<string, Closure> */
    private array $hooks;

    /**
     * @param array<string, Closure> $hooks
     */
    public function __construct(array $hooks)
    {
        $this->hooks = $hooks;
    }

    private function dispatch(string $hook, mixed ...$args): void
    {
        if (isset($this->hooks[$hook])) {
            ($this->hooks[$hook])(...$args);
        }
    }
}
