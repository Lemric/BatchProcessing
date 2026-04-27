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

namespace Lemric\BatchProcessing\Skip;

use Lemric\BatchProcessing\Domain\ExecutionContext;
use Throwable;

/**
 * Tracks skipped items grouped by exception class. The state is persisted into the
 * {@see ExecutionContext} so it survives JVM/PHP-FPM restarts.
 *
 * Storage layout in the ExecutionContext:
 *   "batch.skip.counts" => array<class-string<Throwable>, int>
 */
final class SkipCounter
{
    public const string CONTEXT_KEY = 'batch.skip.counts';

    /**
     * @param array<class-string<Throwable>, int> $counts
     */
    public function __construct(private array $counts = [])
    {
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function get(string $exceptionClass): int
    {
        return $this->counts[$exceptionClass] ?? 0;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function increment(string $exceptionClass): void
    {
        $this->counts[$exceptionClass] = ($this->counts[$exceptionClass] ?? 0) + 1;
    }

    public static function loadFrom(ExecutionContext $context): self
    {
        $raw = $context->get(self::CONTEXT_KEY);
        /** @var array<class-string<Throwable>, int> $counts */
        $counts = is_array($raw) ? array_filter($raw, 'is_int') : [];

        return new self($counts);
    }

    public function persistTo(ExecutionContext $context): void
    {
        $context->put(self::CONTEXT_KEY, $this->counts);
    }

    /**
     * @return array<class-string<Throwable>, int>
     */
    public function toArray(): array
    {
        return $this->counts;
    }

    public function total(): int
    {
        return array_sum($this->counts);
    }
}
