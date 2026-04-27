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

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{BatchStatus, StepContribution, StepExecution};
use Lemric\BatchProcessing\Repeat\{RepeatOperationsInterface, RepeatTemplate};
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;
use Throwable;

/**
 * Adapter step wrapping a single {@see TaskletInterface} call. The tasklet runs inside one
 * transaction managed by the supplied {@see TransactionManagerInterface}; on success the
 * transaction is committed and the StepExecution updated.
 *
 * Optionally integrates with a {@see RepeatOperationsInterface} (stepOperations) to control
 * the outer loop.
 */
final class TaskletStep extends AbstractStep
{
    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        private readonly TaskletInterface $tasklet,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly RepeatOperationsInterface $stepOperations = new RepeatTemplate(),
    ) {
        parent::__construct($name, $jobRepository);
    }

    protected function doExecute(StepExecution $stepExecution): void
    {
        $this->stepOperations->iterate(function () use ($stepExecution): RepeatStatus {
            if ($stepExecution->isTerminateOnly() || $stepExecution->getJobExecution()->isStopping()) {
                $stepExecution->setStatus(BatchStatus::STOPPED);

                return RepeatStatus::FINISHED;
            }

            $contribution = new StepContribution($stepExecution);
            $chunkContext = new ChunkContext($contribution);

            $this->transactionManager->begin();
            try {
                $result = $this->tasklet->execute($contribution, $chunkContext);
                $contribution->apply();
                $stepExecution->incrementCommitCount();
                $this->transactionManager->commit();

                return $result;
            } catch (Throwable $e) {
                $this->transactionManager->rollback();
                $stepExecution->incrementRollbackCount();
                throw $e;
            }
        });
    }
}
