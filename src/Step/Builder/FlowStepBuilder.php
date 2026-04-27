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

use Lemric\BatchProcessing\Job\Flow\FlowInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\{FlowStep, StepInterface};
use LogicException;

/**
 * {@code FlowStepBuilder} parity. Wraps a flow as a step.
 *
 * @extends AbstractStepBuilder<FlowStep>
 */
final class FlowStepBuilder extends AbstractStepBuilder
{
    private ?FlowInterface $flow = null;

    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
    ) {
        parent::__construct($name, $jobRepository);
    }

    public function build(): StepInterface
    {
        if (null === $this->flow) {
            throw new LogicException("FlowStepBuilder for '{$this->name}' requires flow().");
        }

        $step = new FlowStep($this->name, $this->jobRepository, $this->flow);
        $this->applyCommon($step);

        return $step;
    }

    public function flow(FlowInterface $flow): self
    {
        $this->flow = $flow;

        return $this;
    }
}
