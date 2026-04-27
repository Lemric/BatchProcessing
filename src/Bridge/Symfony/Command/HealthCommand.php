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

use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reports the health of the batch system: running executions, failed recent, etc.
 *
 * Usage: `bin/console batch:health`
 */
#[AsCommand(name: 'batch:health', description: 'Show health status of the batch processing system.')]
final class HealthCommand extends Command
{
    public function __construct(private readonly JobExplorerInterface $explorer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Batch Processing Health');

        $names = $this->explorer->getJobNames();

        if ([] === $names) {
            $io->success('No registered jobs found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($names as $name) {
            $running = $this->explorer->findRunningJobExecutions($name);
            $rows[] = [$name, count($running).' running'];
        }

        $io->table(['Job Name', 'Status'], $rows);

        return self::SUCCESS;
    }
}
