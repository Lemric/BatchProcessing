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

namespace Lemric\BatchProcessing\Partition;

use Lemric\BatchProcessing\Domain\{BatchStatus, StepExecution};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\{AbstractStep, StepInterface};

/**
 * Step that splits work into partitions using a {@see PartitionerInterface} and delegates
 * execution to a {@see StepHandlerInterface}. After all partitions complete, the master
 * StepExecution aggregates the results.
 */
final class PartitionStep extends AbstractStep
{
    private int $gridSize = 6;

    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        private readonly PartitionerInterface $partitioner,
        private readonly StepInterface $workerStep,
        private readonly StepHandlerInterface $handler,
    ) {
        parent::__construct($name, $jobRepository);
    }

    public function setGridSize(int $gridSize): void
    {
        $this->gridSize = max(1, $gridSize);
    }

    protected function doExecute(StepExecution $masterStepExecution): void
    {
        $partitions = $this->partitioner->partition($this->gridSize);

        $partitionExecutions = [];
        foreach ($partitions as $partitionName => $partitionContext) {
            $jobExecution = $masterStepExecution->getJobExecution();
            $partitionStepExecution = $jobExecution->createStepExecution(
                $this->workerStep->getName().':'.$partitionName,
            );
            $partitionStepExecution->setExecutionContext(clone $partitionContext);
            $this->jobRepository->add($partitionStepExecution);
            $partitionExecutions[] = $partitionStepExecution;
        }

        $this->handler->handle($this->workerStep, $partitionExecutions);

        // Aggregate results.
        $allCompleted = true;
        foreach ($partitionExecutions as $partitionExec) {
            if ($partitionExec->getStatus()->isUnsuccessful()) {
                $allCompleted = false;
                foreach ($partitionExec->getFailureExceptions() as $ex) {
                    $masterStepExecution->addFailureException($ex);
                }
            }
        }

        if (!$allCompleted) {
            $masterStepExecution->setStatus(BatchStatus::FAILED);
        }
    }
}
