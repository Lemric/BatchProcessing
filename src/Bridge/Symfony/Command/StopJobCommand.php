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
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Lemric\BatchProcessing\Security\{CliInputBounds, JobExecutionAccessCheckerInterface, NoOpJobExecutionAccessChecker};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Requests a graceful stop of a running execution via {@see JobOperatorInterface::stop()}.
 *
 * Usage: `bin/console batch:job:stop 42`
 */
#[AsCommand(name: 'batch:job:stop', description: 'Request a graceful stop of a running job execution.')]
final class StopJobCommand extends Command
{
    private readonly JobExecutionAccessCheckerInterface $executionAccessChecker;

    public function __construct(
        private readonly JobOperatorInterface $operator,
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

        if (!$this->operator->stop($id)) {
            $io->warning("Execution {$id} is not running (or does not exist) — nothing to stop.");

            return self::FAILURE;
        }
        $io->success("Stop requested for execution {$id}.");

        return self::SUCCESS;
    }
}
