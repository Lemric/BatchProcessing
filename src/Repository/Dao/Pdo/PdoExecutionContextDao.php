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

use Lemric\BatchProcessing\Domain\{JobExecution, StepExecution};
use Lemric\BatchProcessing\Repository\Dao\ExecutionContextDaoInterface;
use Lemric\BatchProcessing\Repository\PdoJobRepository;

final readonly class PdoExecutionContextDao implements ExecutionContextDaoInterface
{
    public function __construct(private PdoJobRepository $repository)
    {
    }

    public function persistJobExecutionContext(JobExecution $jobExecution): void
    {
        $this->repository->updateJobExecutionContext($jobExecution);
    }

    public function persistStepExecutionContext(StepExecution $stepExecution): void
    {
        $this->repository->updateExecutionContext($stepExecution);
    }
}
