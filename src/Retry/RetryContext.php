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

namespace Lemric\BatchProcessing\Retry;

use Throwable;

/**
 * Mutable per-execution context tracked by {@see RetryTemplate}. Holds the retry counter and the
 * most recent exception. Exposed to user-supplied callbacks so they can inspect the attempt
 * number.
 */
final class RetryContext
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    private bool $exhausted = false;

    private ?Throwable $lastThrowable = null;

    private int $retryCount = 0;

    public function __construct(private readonly ?RetryContext $parent = null)
    {
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getLastThrowable(): ?Throwable
    {
        return $this->lastThrowable;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function isExhausted(): bool
    {
        return $this->exhausted;
    }

    public function registerThrowable(?Throwable $t): void
    {
        $this->lastThrowable = $t;
        if (null !== $t) {
            ++$this->retryCount;
        }
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setExhausted(): void
    {
        $this->exhausted = true;
    }
}
