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

namespace Lemric\BatchProcessing\Step;

/**
 * Outcome reported by a {@see TaskletInterface}: either the tasklet has finished or it should
 * be invoked again (e.g. for incremental work spread across multiple chunks).
 */
enum RepeatStatus: string
{
    case CONTINUABLE = 'CONTINUABLE';

    case FINISHED = 'FINISHED';

    public static function continueIf(bool $condition): self
    {
        return $condition ? self::CONTINUABLE : self::FINISHED;
    }

    public function isContinuable(): bool
    {
        return self::CONTINUABLE === $this;
    }
}
