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

namespace Lemric\BatchProcessing\Repeat;

/**
 * Per-iteration repeat context propagated through {@see RepeatTemplate}.
 *
 * Holds attributes shared across iteration callbacks, terminate-only flag
 * (graceful stop request) and an optional parent context for nested loops.
 */
final class RepeatContext
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    private bool $completeOnly = false;

    private int $startedCount = 0;

    private bool $terminateOnly = false;

    public function __construct(
        private readonly ?RepeatContext $parent = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function attributeNames(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function getParent(): ?RepeatContext
    {
        return $this->parent;
    }

    public function getStartedCount(): int
    {
        return $this->startedCount;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function increment(): void
    {
        ++$this->startedCount;
    }

    public function isCompleteOnly(): bool
    {
        return $this->completeOnly;
    }

    public function isTerminateOnly(): bool
    {
        return $this->terminateOnly;
    }

    public function removeAttribute(string $name): void
    {
        unset($this->attributes[$name]);
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function setCompleteOnly(): void
    {
        $this->completeOnly = true;
    }

    public function setTerminateOnly(): void
    {
        $this->terminateOnly = true;
    }
}
