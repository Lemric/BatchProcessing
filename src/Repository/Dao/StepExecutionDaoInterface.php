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

use Lemric\BatchProcessing\Domain\{JobInstance, StepExecution};

/**
 * DAO contract for {@see StepExecution} persistence.
 */
interface StepExecutionDaoInterface
{
    public function add(StepExecution $stepExecution): void;

    public function getLastStepExecution(JobInstance $instance, string $stepName): ?StepExecution;

    public function getStepExecutionCount(JobInstance $instance, string $stepName): int;

    public function update(StepExecution $stepExecution): void;
}
