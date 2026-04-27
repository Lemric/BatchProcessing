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

final class RestartJobCommand extends Command
{
    /** @var string */
    protected $description = 'Restart a failed/stopped job execution.';

    /** @var string */
    protected $signature = 'batch:job:restart {executionId : Previous JobExecution id}';

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
            $newId = $operator->restart($id);
        } catch (JobExecutionAccessDeniedException) {
            $this->error('Access denied.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Restart of execution {$id} failed.");

            return self::FAILURE;
        }
        $this->info("Restarted execution {$id} as new execution {$newId}.");

        return self::SUCCESS;
    }
}
