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

namespace Lemric\BatchProcessing\Event;

use Lemric\BatchProcessing\Domain\StepExecution;
use Throwable;

final class StepFailedEvent
{
    public function __construct(
        public readonly StepExecution $stepExecution,
        public readonly Throwable $throwable,
    ) {
    }

    public function getStepExecution(): StepExecution
    {
        return $this->stepExecution;
    }

    public function getThrowable(): Throwable
    {
        return $this->throwable;
    }
}
