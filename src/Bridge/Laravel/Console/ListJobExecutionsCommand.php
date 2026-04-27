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
use Lemric\BatchProcessing\Domain\BatchStatus;
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Security\CliInputBounds;

/**
 * Artisan equivalent of {@see \Lemric\BatchProcessing\Bridge\Symfony\Command\ListJobExecutionsCommand}.
 *
 * Usage: `php artisan batch:job:list --name=importOrdersJob --status=FAILED`
 */
final class ListJobExecutionsCommand extends Command
{
    /** @var string */
    protected $description = 'List job executions.';

    /** @var string */
    protected $signature = 'batch:job:list
        {--name= : Filter by job name}
        {--status= : Filter by BatchStatus}
        {--limit=20 : Max instances to inspect}';

    public function handle(JobExplorerInterface $explorer): int
    {
        /** @var string|null $name */
        $name = $this->option('name');
        /** @var string|null $statusOpt */
        $statusOpt = $this->option('status');
        $limit = (int) $this->option('limit');
        try {
            CliInputBounds::assertListLimit($limit);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $statusFilter = null;
        if (null !== $statusOpt) {
            $statusFilter = BatchStatus::tryFrom($statusOpt);
            if (null === $statusFilter) {
                $this->error("Unknown BatchStatus '{$statusOpt}'.");

                return self::FAILURE;
            }
        }

        $jobNames = null === $name ? $explorer->getJobNames() : [$name];
        $rows = [];
        foreach ($jobNames as $jobName) {
            foreach ($explorer->getJobInstances($jobName, 0, $limit) as $instance) {
                foreach ($explorer->getJobExecutions($instance) as $exec) {
                    if (null !== $statusFilter && $exec->getStatus() !== $statusFilter) {
                        continue;
                    }
                    $rows[] = [
                        'id' => $exec->getId(),
                        'job' => $jobName,
                        'status' => $exec->getStatus()->value,
                        'exit' => $exec->getExitStatus()->getExitCode(),
                        'startedAt' => $exec->getStartTime()?->format('Y-m-d H:i:s') ?? '',
                        'endedAt' => $exec->getEndTime()?->format('Y-m-d H:i:s') ?? '',
                    ];
                }
            }
        }

        if ([] === $rows) {
            $this->line('No job executions matched the filters.');

            return self::SUCCESS;
        }

        $this->table(['id', 'job', 'status', 'exit', 'startedAt', 'endedAt'], $rows);

        return self::SUCCESS;
    }
}
