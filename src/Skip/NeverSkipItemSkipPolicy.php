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
 * Never skip - any failure escalates immediately.
 */
final class NeverSkipItemSkipPolicy implements SkipPolicyInterface
{
    public function shouldSkip(Throwable $t, int $skipCount): bool
    {
        return false;
    }
}
