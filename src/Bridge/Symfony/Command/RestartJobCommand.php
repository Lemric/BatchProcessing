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
use Throwable;

/**
 * Restarts a previously failed/stopped execution by id.
 *
 * Usage: `bin/console batch:job:restart 42`
 */
#[AsCommand(name: 'batch:job:restart', description: 'Restart a failed/stopped job execution.')]
final class RestartJobCommand extends Command
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
        $this->addArgument('executionId', InputArgument::REQUIRED, 'Previous JobExecution id');
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
            $newId = $this->operator->restart($id);
        } catch (JobExecutionAccessDeniedException) {
            $io->error('Access denied.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $io->error("Restart of execution {$id} failed.");

            return self::FAILURE;
        }
        $io->success("Restarted execution {$id} as new execution {$newId}.");

        return self::SUCCESS;
    }
}
