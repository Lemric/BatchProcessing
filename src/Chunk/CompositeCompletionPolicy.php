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

namespace Lemric\BatchProcessing\Chunk;

/**
 * Combines multiple completion policies using logical AND or OR.
 */
final class CompositeCompletionPolicy implements CompletionPolicyInterface
{
    /**
     * @param list<CompletionPolicyInterface> $policies
     * @param bool                            $requireAll true = AND (all must be complete), false = OR (any must be complete)
     */
    public function __construct(
        private readonly array $policies,
        private readonly bool $requireAll = false,
    ) {
    }

    public function isComplete(ChunkContext $context, mixed $result = null): bool
    {
        if (null === $result) {
            return true;
        }

        if ($this->requireAll) {
            foreach ($this->policies as $policy) {
                if (!$policy->isComplete($context, $result)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($this->policies as $policy) {
            if ($policy->isComplete($context, $result)) {
                return true;
            }
        }

        return false;
    }

    public function start(ChunkContext $context): void
    {
        foreach ($this->policies as $policy) {
            $policy->start($context);
        }
    }

    public function update(ChunkContext $context): void
    {
        foreach ($this->policies as $policy) {
            $policy->update($context);
        }
    }
}
