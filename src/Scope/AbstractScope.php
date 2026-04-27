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

namespace Lemric\BatchProcessing\Scope;

use Lemric\BatchProcessing\Exception\ScopeNotActiveException;

/**
 * Base class for lazy-initialization scoped proxies.
 * Manages the active flag and the single-instance lifecycle shared by
 * {@see JobScope} and {@see StepScope}.
 *
 * @template T
 */
abstract class AbstractScope
{
    protected mixed $instance = null;

    private bool $active = false;

    /**
     * Activates the scope.
     */
    public function activate(): void
    {
        $this->active = true;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Resets the scoped instance (called at end of step/job).
     */
    public function reset(): void
    {
        $this->instance = null;
        $this->active = false;
    }

    /**
     * Ensures the scope is active before returning an instance.
     *
     * @throws ScopeNotActiveException
     */
    protected function ensureActive(string $scopeName): void
    {
        if (!$this->active) {
            throw new ScopeNotActiveException("{$scopeName} is not active. Ensure the scope is activated before calling get().");
        }
    }
}
