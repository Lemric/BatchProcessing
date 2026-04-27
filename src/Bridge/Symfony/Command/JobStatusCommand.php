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

namespace Lemric\BatchProcessing\Bridge\Symfony\Command;

use InvalidArgumentException;
use Lemric\BatchProcessing\Exception\JobExecutionAccessDeniedException;
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Security\{CliInputBounds, JobExecutionAccessCheckerInterface, NoOpJobExecutionAccessChecker, SensitiveDataSanitizer};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Style\SymfonyStyle;

use const DATE_ATOM;

/**
 * Inspects a single {@see \Lemric\BatchProcessing\Domain\JobExecution} including all of its
 * {@see \Lemric\BatchProcessing\Domain\StepExecution}s.
 *
 * Usage: `bin/console batch:job:status 42`
 */
#[AsCommand(name: 'batch:job:status', description: 'Show status of a job execution.')]
final class JobStatusCommand extends Command
{
    private readonly JobExecutionAccessCheckerInterface $executionAccessChecker;

    public function __construct(
        private readonly JobExplorerInterface $explorer,
        ?JobExecutionAccessCheckerInterface $executionAccessChecker = null,
    ) {
        parent::__construct();
        $this->executionAccessChecker = $executionAccessChecker ?? new NoOpJobExecutionAccessChecker();
    }

    protected function configure(): void
    {
        $this->addArgument('executionId', InputArgument::REQUIRED, 'JobExecution id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var int|string $idArg */
        $idArg = $input->getArgument('executionId');
        $id = (int) $idArg;
        try {
            CliInputBounds::assertExecutionId($id);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $this->executionAccessChecker->assertMayAccessJobExecution($id);
        } catch (JobExecutionAccessDeniedException) {
            $io->error('Access denied.');

            return self::FAILURE;
        }

        $execution = $this->explorer->getJobExecution($id);
        if (null === $execution) {
            $io->error("No JobExecution {$id} found.");

            return self::FAILURE;
        }

        $exitDescription = $execution->getExitStatus()->getExitDescription();
        if ($output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            $exitDescription = SensitiveDataSanitizer::sanitize($exitDescription);
        }

        $io->definitionList(
            ['id' => (string) $execution->getId()],
            ['job' => $execution->getJobName()],
            ['status' => $execution->getStatus()->value],
            ['exitCode' => $execution->getExitStatus()->getExitCode()],
            ['exitDescription' => $exitDescription],
            ['startedAt' => $execution->getStartTime()?->format(DATE_ATOM) ?? '-'],
            ['endedAt' => $execution->getEndTime()?->format(DATE_ATOM) ?? '-'],
        );

        $rows = [];
        foreach ($execution->getStepExecutions() as $step) {
            $rows[] = [
                $step->getId(),
                $step->getStepName(),
                $step->getStatus()->value,
                $step->getExitStatus()->getExitCode(),
                $step->getReadCount(),
                $step->getWriteCount(),
                $step->getSkipCount(),
            ];
        }
        if ([] !== $rows) {
            $io->table(['id', 'step', 'status', 'exit', 'read', 'write', 'skip'], $rows);
        }

        return self::SUCCESS;
    }
}
