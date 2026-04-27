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

use Lemric\BatchProcessing\Domain\StepExecution;
use Lemric\BatchProcessing\Exception\{SkipLimitExceededException, SkippableException};
use Throwable;

use const PHP_INT_MAX;

/**
 * Decorator over {@see SkipPolicyInterface} that maintains
 * per-exception-class skip counters in the StepExecution's {@see ExecutionContext}.
 *
 * Use when you need a per-class limit (rather than a single global limit) or want skip
 * statistics persisted across restarts.
 */
final readonly class CountingSkipPolicy implements SkipPolicyInterface
{
    /**
     * @param array<class-string<Throwable>, int> $perClassLimits
     */
    public function __construct(
        private StepExecution $stepExecution,
        private array $perClassLimits,
        private int $globalLimit = PHP_INT_MAX,
    ) {
    }

    public function shouldSkip(Throwable $t, int $skipCount): bool
    {
        $matched = $t instanceof SkippableException;
        $matchedClass = null;
        if (!$matched) {
            foreach (array_keys($this->perClassLimits) as $class) {
                if ($t instanceof $class) {
                    $matched = true;
                    $matchedClass = $class;
                    break;
                }
            }
        }
        if (!$matched) {
            return false;
        }

        $context = $this->stepExecution->getExecutionContext();
        $counter = SkipCounter::loadFrom($context);

        $effectiveClass = $matchedClass ?? get_class($t);
        $perClassCount = $counter->get($effectiveClass);
        $perClassLimit = $this->perClassLimits[$effectiveClass] ?? $this->globalLimit;

        if ($perClassCount >= $perClassLimit || $counter->total() >= $this->globalLimit) {
            throw new SkipLimitExceededException(min($perClassLimit, $this->globalLimit), previous: $t);
        }

        $counter->increment($effectiveClass);
        $counter->persistTo($context);

        return true;
    }
}
