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
 * In-process reference {@see ScopedContainerInterface}. Holds factories in a flat map and
 * caches resolved instances inside a stack of scope frames. Suitable for tests and for
 * embedding into integrations that do not provide a real PSR-11 container.
 */
final class InMemoryScopedContainer implements ScopedContainerInterface
{
    /** @var array<string, callable(): object> */
    private array $factories = [];

    /** @var array<string, array<string, object>> */
    private array $instances = [];

    /** @var list<string> */
    private array $scopeStack = [];

    public function enterScope(string $scopeId): void
    {
        $this->scopeStack[] = $scopeId;
        $this->instances[$scopeId] ??= [];
    }

    public function exitScope(string $scopeId): void
    {
        $key = array_search($scopeId, $this->scopeStack, true);
        if (false !== $key) {
            array_splice($this->scopeStack, $key, 1);
        }
    }

    public function get(string $serviceId): object
    {
        $scope = $this->getCurrentScopeId();
        if (null === $scope) {
            throw new RuntimeException('No scope is currently active.');
        }
        if (!isset($this->factories[$serviceId])) {
            throw new RuntimeException("Unknown scoped service: {$serviceId}");
        }

        return $this->instances[$scope][$serviceId] ??= ($this->factories[$serviceId])();
    }

    public function getCurrentScopeId(): ?string
    {
        if ([] === $this->scopeStack) {
            return null;
        }

        return $this->scopeStack[count($this->scopeStack) - 1];
    }

    public function has(string $serviceId): bool
    {
        return isset($this->factories[$serviceId]);
    }

    public function register(string $serviceId, callable $factory): void
    {
        $this->factories[$serviceId] = $factory;
    }

    public function resetScope(string $scopeId): void
    {
        unset($this->instances[$scopeId]);
        $this->exitScope($scopeId);
    }
}
