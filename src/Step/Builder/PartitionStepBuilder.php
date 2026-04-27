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

namespace Lemric\BatchProcessing\Step\Builder;

use Lemric\BatchProcessing\Partition\{PartitionStep, PartitionerInterface, StepHandlerInterface, TaskExecutorPartitionHandler};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\StepInterface;
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;
use LogicException;

/**
 * {@code PartitionStepBuilder} parity.
 *
 * @extends AbstractStepBuilder<PartitionStep>
 */
final class PartitionStepBuilder extends AbstractStepBuilder
{
    private int $gridSize = 6;

    private ?PartitionerInterface $partitioner = null;

    private ?StepHandlerInterface $partitionHandler = null;

    private ?StepInterface $workerStep = null;

    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        ?TransactionManagerInterface $transactionManager = null,
    ) {
        parent::__construct($name, $jobRepository, $transactionManager);
    }

    public function build(): StepInterface
    {
        if (null === $this->partitioner) {
            throw new LogicException("PartitionStepBuilder for '{$this->name}' requires partitioner().");
        }
        if (null === $this->workerStep) {
            throw new LogicException("PartitionStepBuilder for '{$this->name}' requires workerStep().");
        }

        $step = new PartitionStep(
            $this->name,
            $this->jobRepository,
            $this->partitioner,
            $this->workerStep,
            $this->partitionHandler ?? new TaskExecutorPartitionHandler(),
        );
        $step->setGridSize($this->gridSize);

        $this->applyCommon($step);

        return $step;
    }

    public function gridSize(int $gridSize): self
    {
        $this->gridSize = max(1, $gridSize);

        return $this;
    }

    public function partitioner(PartitionerInterface $partitioner): self
    {
        $this->partitioner = $partitioner;

        return $this;
    }

    public function partitionHandler(StepHandlerInterface $handler): self
    {
        $this->partitionHandler = $handler;

        return $this;
    }

    public function workerStep(StepInterface $workerStep): self
    {
        $this->workerStep = $workerStep;

        return $this;
    }
}
