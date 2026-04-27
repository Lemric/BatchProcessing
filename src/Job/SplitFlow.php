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

namespace Lemric\BatchProcessing\Job;

use Fiber;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobExecution};
use Lemric\BatchProcessing\Step\StepInterface;
use Throwable;

/**
 * Executes multiple steps concurrently using PHP Fibers.
 * Each step runs in its own Fiber, yielding cooperatively.
 */
final class SplitFlow
{
    /** @var list<StepInterface> */
    private readonly array $steps;

    public function __construct(StepInterface ...$steps)
    {
        $this->steps = array_values($steps);
    }

    /**
     * Executes all steps concurrently. Each step gets its own StepExecution.
     * Collects all errors and upgrades the job status accordingly.
     */
    public function execute(JobExecution $jobExecution): void
    {
        if ([] === $this->steps) {
            return;
        }

        /** @var list<Fiber<void, void, void, void>> $fibers */
        $fibers = [];
        /** @var list<Throwable> $errors */
        $errors = [];

        foreach ($this->steps as $step) {
            $stepExecution = $jobExecution->createStepExecution($step->getName());
            $fiber = new Fiber(static function () use ($step, $stepExecution): void {
                $step->execute($stepExecution);
            });
            $fibers[] = $fiber;
        }

        // Start all fibers
        foreach ($fibers as $fiber) {
            try {
                $fiber->start();
            } catch (Throwable $e) {
                $errors[] = $e;
            }
        }

        // Resume fibers until all complete
        $running = true;
        while ($running) {
            $running = false;
            foreach ($fibers as $fiber) {
                if (!$fiber->isTerminated()) {
                    $running = true;
                    if ($fiber->isSuspended()) {
                        try {
                            $fiber->resume();
                        } catch (Throwable $e) {
                            $errors[] = $e;
                        }
                    }
                }
            }
        }

        // Upgrade job status based on step results
        foreach ($jobExecution->getStepExecutions() as $stepExecution) {
            if ($stepExecution->getStatus()->isUnsuccessful()) {
                $jobExecution->setStatus($jobExecution->getStatus()->upgradeTo($stepExecution->getStatus()));
            }
        }

        if ([] !== $errors) {
            $jobExecution->setStatus(BatchStatus::FAILED);
            foreach ($errors as $error) {
                $jobExecution->addFailureException($error);
            }
        }
    }

    /**
     * @return list<StepInterface>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }
}
