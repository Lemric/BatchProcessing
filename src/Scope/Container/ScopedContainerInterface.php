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

namespace Lemric\BatchProcessing\Scope\Container;

use RuntimeException;

/**
 * PSR-11-style narrow contract for a per-Step / per-Job scoped container. Each scope is keyed
 * by an opaque identifier (typically a StepExecution / JobExecution id) and may be reset
 * independently of other scopes.
 */
interface ScopedContainerInterface
{
    public function enterScope(string $scopeId): void;

    public function exitScope(string $scopeId): void;

    /**
     * @throws RuntimeException when no scope is active or the service is unknown
     */
    public function get(string $serviceId): object;

    public function getCurrentScopeId(): ?string;

    public function has(string $serviceId): bool;

    /**
     * Registers a factory callable for a service id. The factory is invoked at most once per
     * scope id (results are cached until {@see resetScope()} is called).
     *
     * @param callable(): object $factory
     */
    public function register(string $serviceId, callable $factory): void;

    public function resetScope(string $scopeId): void;
}
