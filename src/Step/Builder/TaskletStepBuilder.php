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
use Lemric\BatchProcessing\Step\{StepInterface, TaskletInterface, TaskletStep};
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;
use LogicException;

/**
 * {@code TaskletStepBuilder} parity. Wraps a single {@see TaskletInterface}
 * call inside a transaction.
 *
 * @extends AbstractStepBuilder<TaskletStep>
 */
final class TaskletStepBuilder extends AbstractStepBuilder
{
    private ?TaskletInterface $tasklet = null;

    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        ?TransactionManagerInterface $transactionManager = null,
    ) {
        parent::__construct($name, $jobRepository, $transactionManager);
    }

    public function build(): StepInterface
    {
        if (null === $this->tasklet) {
            throw new LogicException("TaskletStepBuilder for '{$this->name}' requires tasklet().");
        }

        $step = new TaskletStep(
            $this->name,
            $this->jobRepository,
            $this->tasklet,
            $this->transactionManager,
        );

        $this->applyCommon($step);

        return $step;
    }

    public function tasklet(TaskletInterface $tasklet): self
    {
        $this->tasklet = $tasklet;

        return $this;
    }
}
