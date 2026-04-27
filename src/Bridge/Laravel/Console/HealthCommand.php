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
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;

class HealthCommand extends Command
{
    protected $description = 'Show health status of the batch processing system.';

    protected $signature = 'batch:health';

    public function handle(JobExplorerInterface $explorer): int
    {
        $this->info('Batch Processing Health');

        $names = $explorer->getJobNames();

        if ([] === $names) {
            $this->info('No registered jobs found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($names as $name) {
            $running = $explorer->findRunningJobExecutions($name);
            $rows[] = [$name, count($running).' running'];
        }

        $this->table(['Job Name', 'Status'], $rows);

        return self::SUCCESS;
    }
}
