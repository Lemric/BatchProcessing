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

namespace Lemric\BatchProcessing\Step;

use Lemric\BatchProcessing\Domain\{BatchStatus, StepExecution};
use Lemric\BatchProcessing\Job\Flow\{FlowInterface};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Step that wraps a {@see FlowInterface} for embedding inside another Job.
 */
final class FlowStep extends AbstractStep
{
    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        private readonly FlowInterface $flow,
    ) {
        parent::__construct($name, $jobRepository);
    }

    protected function doExecute(StepExecution $stepExecution): void
    {
        $status = $this->flow->start($stepExecution->getJobExecution());

        if ($status->isFail()) {
            $stepExecution->setStatus(BatchStatus::FAILED);
        } elseif ($status->isStop()) {
            $stepExecution->setStatus(BatchStatus::STOPPED);
        }
    }
}
