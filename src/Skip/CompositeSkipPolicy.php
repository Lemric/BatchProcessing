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

use Throwable;

/**
 * Delegates to an ordered list of skip policies. Returns true if ANY policy allows the skip.
 */
final class CompositeSkipPolicy implements SkipPolicyInterface
{
    /**
     * @param list<SkipPolicyInterface> $policies
     */
    public function __construct(
        private readonly array $policies,
    ) {
    }

    public function shouldSkip(Throwable $t, int $skipCount): bool
    {
        foreach ($this->policies as $policy) {
            if ($policy->shouldSkip($t, $skipCount)) {
                return true;
            }
        }

        return false;
    }
}
