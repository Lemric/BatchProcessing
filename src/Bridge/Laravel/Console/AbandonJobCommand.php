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
use Throwable;

class AbandonJobCommand extends Command
{
    protected $description = 'Mark a stopped job execution as abandoned (non-restartable).';

    protected $signature = 'batch:job:abandon {executionId : The job execution ID to abandon}';

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
            $operator->abandon($id);
            $this->info("Execution {$id} marked as ABANDONED.");

            return self::SUCCESS;
        } catch (JobExecutionAccessDeniedException) {
            $this->error('Access denied.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Abandon operation failed.');

            return self::FAILURE;
        }
    }
}
