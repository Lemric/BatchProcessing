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

use DateTimeImmutable;
use Exception;
use Illuminate\Console\Command;
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Exception\JobExecutionAccessDeniedException;
use Lemric\BatchProcessing\Job\IdentifyingJobParametersValidator;
use Lemric\BatchProcessing\Launcher\{AsyncJobLauncher, SimpleJobLauncher};
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Lemric\BatchProcessing\Registry\JobRegistryInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Security\JobExecutionAccessCheckerInterface;
use Throwable;

/**
 * Artisan equivalent of {@see \Lemric\BatchProcessing\Bridge\Symfony\Command\LaunchJobCommand}.
 *
 * Usage:
 *  php artisan batch:job:launch importOrdersJob --param=date:2025-01-01 --param=run.id:1
 *
 * Security: restrict Artisan access in production; launching a job runs its business logic.
 */
final class LaunchJobCommand extends Command
{
    /** @var string */
    protected $description = 'Launch a batch job by name.';

    /** @var string */
    protected $signature = 'batch:job:launch
        {jobName : Registered job name}
        {--param=* : key:value job parameter (repeatable)}
        {--next : Use the configured incrementer to derive the next instance}
        {--inline : Force in-process synchronous execution (SimpleJobLauncher).}
        {--async : Force asynchronous execution (AsyncJobLauncher / queue).}
        {--dry-run : Validate parameters without launching the job.}
        {--restart= : Restart the given JobExecution id (shortcut for batch:job:restart).}
        {--interactive|i : Prompt interactively for job parameters.}';

    public function handle(
        JobOperatorInterface $operator,
        JobExecutionAccessCheckerInterface $executionAccessChecker,
        ?JobRegistryInterface $registry = null,
        ?JobRepositoryInterface $repository = null,
        ?SimpleJobLauncher $inlineLauncher = null,
        ?AsyncJobLauncher $asyncLauncher = null,
    ): int {
        /** @var string $jobName */
        $jobName = $this->argument('jobName');

        $restartId = $this->option('restart');
        if (null !== $restartId && '' !== $restartId) {
            try {
                $priorId = (int) $restartId;
                $executionAccessChecker->assertMayAccessJobExecution($priorId);
                $id = $operator->restart($priorId);
                $this->info(sprintf('Job "%s" restarted (new executionId=%d).', $jobName, $id));

                return self::SUCCESS;
            } catch (JobExecutionAccessDeniedException) {
                $this->error('Access denied.');

                return self::FAILURE;
            } catch (Throwable $e) {
                $this->error('Restart failed.');

                return self::FAILURE;
            }
        }

        if ((bool) $this->option('inline') && (bool) $this->option('async')) {
            $this->error('Options --inline and --async are mutually exclusive.');

            return self::FAILURE;
        }

        try {
            if ((bool) $this->option('next')) {
                if ((bool) $this->option('dry-run')) {
                    $this->info(sprintf('[dry-run] --next would launch the next instance of "%s".', $jobName));

                    return self::SUCCESS;
                }
                $id = $operator->startNextInstance($jobName);
            } else {
                /** @var list<string> $rawParams */
                $rawParams = (array) $this->option('param');
                $parsed = self::parseParams($rawParams);
                if ((bool) $this->option('interactive')) {
                    $parsed = array_merge($parsed, $this->promptParams());
                }
                $parameters = JobParameters::of($parsed);

                if ((bool) $this->option('dry-run')) {
                    if (null !== $repository) {
                        new IdentifyingJobParametersValidator($jobName, $repository)->validate($parameters);
                    }
                    $this->info(sprintf('[dry-run] Parameters valid for "%s": %s', $jobName, $parameters->toIdentifyingString()));

                    return self::SUCCESS;
                }

                if (null !== $registry && (bool) $this->option('inline') && null !== $inlineLauncher) {
                    $execution = $inlineLauncher->run($registry->getJob($jobName), $parameters);
                    $id = (int) $execution->getId();
                } elseif (null !== $registry && (bool) $this->option('async') && null !== $asyncLauncher) {
                    $execution = $asyncLauncher->run($registry->getJob($jobName), $parameters);
                    $id = (int) $execution->getId();
                } else {
                    $id = $operator->start($jobName, $parameters);
                }
            }
        } catch (JobExecutionAccessDeniedException) {
            $this->error('Access denied.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error(sprintf('Job "%s" failed to launch.', $jobName));

            return self::FAILURE;
        }

        $this->info(sprintf('Job "%s" launched (executionId=%d).', $jobName, $id));

        return self::SUCCESS;
    }

    /**
     * @param list<string> $raw
     *
     * @return array<string, string|int|float|DateTimeImmutable>
     */
    private static function parseParams(array $raw): array
    {
        $out = [];
        foreach ($raw as $entry) {
            $pos = mb_strpos($entry, ':');
            if (false === $pos) {
                $out[$entry] = '';

                continue;
            }
            $key = mb_substr($entry, 0, $pos);
            $value = mb_substr($entry, $pos + 1);

            if (preg_match('/^-?\d+$/', $value)) {
                $out[$key] = (int) $value;
            } elseif (preg_match('/^-?\d+\.\d+$/', $value)) {
                $out[$key] = (float) $value;
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $value)) {
                try {
                    $out[$key] = new DateTimeImmutable($value);
                } catch (Exception) {
                    $out[$key] = $value;
                }
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function promptParams(): array
    {
        $params = [];
        $this->line('Interactive job parameters (leave key empty to finish):');
        while (true) {
            /** @var string|null $rawKey */
            $rawKey = $this->ask('parameter key', '');
            $key = (string) ($rawKey ?? '');
            if ('' === $key) {
                break;
            }
            /** @var string|null $rawValue */
            $rawValue = $this->ask(sprintf('value for "%s"', $key), '');
            $params[$key] = (string) ($rawValue ?? '');
        }

        return $params;
    }
}
