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
use Lemric\BatchProcessing\Domain\BatchStatus;
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Security\CliInputBounds;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists job executions, optionally filtered by job name and/or {@see BatchStatus}.
 *
 * Usage: `bin/console batch:job:list --name=importOrdersJob --status=FAILED`
 */
#[AsCommand(name: 'batch:job:list', description: 'List job executions.')]
final class ListJobExecutionsCommand extends Command
{
    public function __construct(private readonly JobExplorerInterface $explorer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Filter by job name')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by BatchStatus (e.g. FAILED, COMPLETED)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max instances to inspect', '20')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|null $name */
        $name = $input->getOption('name');
        /** @var string|null $statusOpt */
        $statusOpt = $input->getOption('status');
        /** @var string $limitRaw */
        $limitRaw = $input->getOption('limit');
        $limit = (int) $limitRaw;
        try {
            CliInputBounds::assertListLimit($limit);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $statusFilter = null;
        if (null !== $statusOpt) {
            $statusFilter = BatchStatus::tryFrom($statusOpt);
            if (null === $statusFilter) {
                $io->error("Unknown BatchStatus '{$statusOpt}'.");

                return self::FAILURE;
            }
        }

        $jobNames = null === $name ? $this->explorer->getJobNames() : [$name];
        $rows = [];
        foreach ($jobNames as $jobName) {
            foreach ($this->explorer->getJobInstances($jobName, 0, $limit) as $instance) {
                foreach ($this->explorer->getJobExecutions($instance) as $exec) {
                    if (null !== $statusFilter && $exec->getStatus() !== $statusFilter) {
                        continue;
                    }
                    $rows[] = [
                        $exec->getId(),
                        $jobName,
                        $exec->getStatus()->value,
                        $exec->getExitStatus()->getExitCode(),
                        $exec->getStartTime()?->format('Y-m-d H:i:s') ?? '',
                        $exec->getEndTime()?->format('Y-m-d H:i:s') ?? '',
                    ];
                }
            }
        }

        if ([] === $rows) {
            $io->writeln('<comment>No job executions matched the filters.</comment>');

            return self::SUCCESS;
        }

        $io->table(['id', 'job', 'status', 'exit', 'startedAt', 'endedAt'], $rows);

        return self::SUCCESS;
    }
}
