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

abstract class AbstractStepEvent
{
    public function __construct(public readonly StepExecution $stepExecution)
    {
    }

    public function getStepExecution(): StepExecution
    {
        return $this->stepExecution;
    }
}
