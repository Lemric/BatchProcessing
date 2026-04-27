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

namespace Lemric\BatchProcessing\Repository\Dao;

use Lemric\BatchProcessing\Domain\{JobExecution, StepExecution};

/**
 * DAO contract for {@see \Lemric\BatchProcessing\Domain\ExecutionContext} persistence.
 */
interface ExecutionContextDaoInterface
{
    public function persistJobExecutionContext(JobExecution $jobExecution): void;

    public function persistStepExecutionContext(StepExecution $stepExecution): void;
}
