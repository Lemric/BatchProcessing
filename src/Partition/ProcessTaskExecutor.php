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
use Lemric\BatchProcessing\Exception\StepExecutionException;
use Lemric\BatchProcessing\Step\StepInterface;

use Throwable;

use function extension_loaded;
use function pcntl_fork;
use function pcntl_waitpid;

/**
 * Process-based partition handler for CPU-bound workloads. Uses {@code pcntl_fork()} to execute
 * each partition in a separate child process.
 *
 * Falls back to sequential execution when the {@code pcntl} extension is unavailable.
 *
 * **Caveat:** child processes do NOT share database connections or in-memory state with the
 * parent. Each child must establish its own resources. The parent waits for all children to
 * complete and marks failed partitions based on exit codes.
 */
final class ProcessTaskExecutor implements StepHandlerInterface
{
    public function __construct(
        private readonly int $maxConcurrent = 4,
    ) {
    }

    /**
     * @param list<StepExecution> $partitionStepExecutions
     */
    public function handle(StepInterface $step, array $partitionStepExecutions): void
    {
        if (!extension_loaded('pcntl')) {
            // Fallback: sequential execution.
            foreach ($partitionStepExecutions as $stepExecution) {
                $step->execute($stepExecution);
            }

            return;
        }

        $this->forkAndExecute($step, $partitionStepExecutions);
    }

    /**
     * @param list<StepExecution> $partitionStepExecutions
     */
    private function forkAndExecute(StepInterface $step, array $partitionStepExecutions): void
    {
        /** @var array<int, StepExecution> $pidMap pid => StepExecution */
        $pidMap = [];
        $pending = $partitionStepExecutions;
        $failed = false;

        while ([] !== $pending || [] !== $pidMap) {
            // Fork up to maxConcurrent children.
            while (count($pidMap) < $this->maxConcurrent && [] !== $pending) {
                $stepExecution = array_shift($pending);
                $pid = pcntl_fork();

                if (-1 === $pid) {
                    // Fork failed — run inline.
                    $step->execute($stepExecution);
                    continue;
                }

                if (0 === $pid) {
                    // Child process.
                    try {
                        $step->execute($stepExecution);
                        exit(0);
                    } catch (Throwable) {
                        exit(1);
                    }
                }

                // Parent process.
                $pidMap[$pid] = $stepExecution;
            }

            // Wait for at least one child.
            if ([] !== $pidMap) {
                $status = 0;
                $exitedPid = pcntl_waitpid(-1, $status);
                if ($exitedPid > 0 && isset($pidMap[$exitedPid])) {
                    /** @var int $status */
                    $exitCode = pcntl_wexitstatus($status);
                    if (0 !== $exitCode) {
                        $pidMap[$exitedPid]->setStatus(BatchStatus::FAILED);
                        $failed = true;
                    }
                    unset($pidMap[$exitedPid]);
                }
            }
        }

        if ($failed) {
            throw new StepExecutionException('One or more partitions failed in child processes.');
        }
    }
}
