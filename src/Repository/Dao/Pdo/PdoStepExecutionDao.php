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

namespace Lemric\BatchProcessing\Repository\Dao\Pdo;

use Lemric\BatchProcessing\Domain\{JobInstance, StepExecution};
use Lemric\BatchProcessing\Repository\Dao\StepExecutionDaoInterface;
use Lemric\BatchProcessing\Repository\PdoJobRepository;

final readonly class PdoStepExecutionDao implements StepExecutionDaoInterface
{
    public function __construct(private PdoJobRepository $repository)
    {
    }

    public function add(StepExecution $stepExecution): void
    {
        $this->repository->add($stepExecution);
    }

    public function getLastStepExecution(JobInstance $instance, string $stepName): ?StepExecution
    {
        return $this->repository->getLastStepExecution($instance, $stepName);
    }

    public function getStepExecutionCount(JobInstance $instance, string $stepName): int
    {
        return $this->repository->getStepExecutionCount($instance, $stepName);
    }

    public function update(StepExecution $stepExecution): void
    {
        $this->repository->update($stepExecution);
    }
}
