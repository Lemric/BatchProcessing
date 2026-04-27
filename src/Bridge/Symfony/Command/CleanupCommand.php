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

use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes old/abandoned job executions from the repository.
 *
 * Usage: `bin/console batch:cleanup --days=30`
 */
#[AsCommand(name: 'batch:cleanup', description: 'Clean up old or abandoned job execution metadata.')]
final class CleanupCommand extends Command
{
    public function __construct(
        private readonly JobOperatorInterface $operator, // reserved for future cleanup implementation
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Remove executions older than N days', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|int $rawDays */
        $rawDays = $input->getOption('days');
        $days = (int) $rawDays;

        $registeredJobs = count($this->operator->getJobNames());
        $io->info("Cleanup of executions older than {$days} days is not yet implemented in the repository layer ({$registeredJobs} job name(s) registered in the operator).");
        $io->note('This command is a placeholder — extend your JobOperator or Repository to support deletion.');

        return self::SUCCESS;
    }
}
