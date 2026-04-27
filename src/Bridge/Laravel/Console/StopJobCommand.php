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
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Lemric\BatchProcessing\Security\{CliInputBounds, JobExecutionAccessCheckerInterface};

final class StopJobCommand extends Command
{
    /** @var string */
    protected $description = 'Request a graceful stop of a running job execution.';

    /** @var string */
    protected $signature = 'batch:job:stop {executionId : JobExecution id}';

    public function handle(JobOperatorInterface $operator, JobExecutionAccessCheckerInterface $executionAccessChecker): int
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

        if (!$operator->stop($id)) {
            $this->warn("Execution {$id} is not running (or does not exist) — nothing to stop.");

            return self::FAILURE;
        }
        $this->info("Stop requested for execution {$id}.");

        return self::SUCCESS;
    }
}
