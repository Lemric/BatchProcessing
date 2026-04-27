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

namespace Lemric\BatchProcessing\Bridge\Laravel\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Lemric\BatchProcessing\Exception\JobExecutionAccessDeniedException;
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Security\{CliInputBounds, JobExecutionAccessCheckerInterface, SensitiveDataSanitizer};

use const DATE_ATOM;

/**
 * Artisan equivalent of {@see \Lemric\BatchProcessing\Bridge\Symfony\Command\JobStatusCommand}.
 */
final class JobStatusCommand extends Command
{
    /** @var string */
    protected $description = 'Show status of a job execution.';

    /** @var string */
    protected $signature = 'batch:job:status {executionId : JobExecution id}';

    public function handle(JobExplorerInterface $explorer, JobExecutionAccessCheckerInterface $executionAccessChecker): int
    {
        $id = (int) $this->argument('executionId');
        try {
            CliInputBounds::assertExecutionId($id);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $executionAccessChecker->assertMayAccessJobExecution($id);
        } catch (JobExecutionAccessDeniedException) {
            $this->error('Access denied.');

            return self::FAILURE;
        }

        $execution = $explorer->getJobExecution($id);
        if (null === $execution) {
            $this->error("No JobExecution {$id} found.");

            return self::FAILURE;
        }

        $exitDescription = $execution->getExitStatus()->getExitDescription();
        if (!$this->output->isVerbose()) {
            $exitDescription = SensitiveDataSanitizer::sanitize($exitDescription);
        }

        $this->table(['property', 'value'], [
            ['id', (string) $execution->getId()],
            ['job', $execution->getJobName()],
            ['status', $execution->getStatus()->value],
            ['exitCode', $execution->getExitStatus()->getExitCode()],
            ['exitDescription', $exitDescription],
            ['startedAt', $execution->getStartTime()?->format(DATE_ATOM) ?? '-'],
            ['endedAt', $execution->getEndTime()?->format(DATE_ATOM) ?? '-'],
        ]);

        $rows = [];
        foreach ($execution->getStepExecutions() as $step) {
            $rows[] = [
                'id' => $step->getId(),
                'step' => $step->getStepName(),
                'status' => $step->getStatus()->value,
                'exit' => $step->getExitStatus()->getExitCode(),
                'read' => $step->getReadCount(),
                'write' => $step->getWriteCount(),
                'skip' => $step->getSkipCount(),
            ];
        }
        if ([] !== $rows) {
            $this->table(['id', 'step', 'status', 'exit', 'read', 'write', 'skip'], $rows);
        }

        return self::SUCCESS;
    }
}
