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

use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\{AbstractStep, StepInterface};
use Lemric\BatchProcessing\Transaction\{ResourcelessTransactionManager, TransactionManagerInterface};

use const PHP_INT_MAX;

/**
 * Shared plumbing
 * ({@see SimpleStepBuilder}, {@see FaultTolerantStepBuilder}, {@see TaskletStepBuilder},
 * {@see PartitionStepBuilder}, {@see JobStepBuilder}, {@see FlowStepBuilder}).
 *
 * @template TStep of StepInterface
 */
abstract class AbstractStepBuilder
{
    protected bool $allowStartIfComplete = false;

    /** @var list<object> */
    protected array $listeners = [];

    protected int $startLimit = PHP_INT_MAX;

    protected TransactionManagerInterface $transactionManager;

    public function __construct(
        protected readonly string $name,
        protected readonly JobRepositoryInterface $jobRepository,
        ?TransactionManagerInterface $transactionManager = null,
    ) {
        $this->transactionManager = $transactionManager ?? new ResourcelessTransactionManager();
    }

    public function allowStartIfComplete(bool $value = true): static
    {
        $this->allowStartIfComplete = $value;

        return $this;
    }

    /**
     * @return TStep
     */
    abstract public function build(): StepInterface;

    public function listener(object $listener): static
    {
        $this->listeners[] = $listener;

        return $this;
    }

    public function startLimit(int $limit): static
    {
        $this->startLimit = $limit;

        return $this;
    }

    public function transactionManager(TransactionManagerInterface $manager): static
    {
        $this->transactionManager = $manager;

        return $this;
    }

    protected function applyCommon(StepInterface $step): void
    {
        if ($step instanceof AbstractStep) {
            $step->setAllowStartIfComplete($this->allowStartIfComplete);
            $step->setStartLimit($this->startLimit);
            foreach ($this->listeners as $listener) {
                $step->registerListener($listener);
            }
        }
    }
}
