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

use DateTimeImmutable;
use Exception;
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Exception\JobExecutionAccessDeniedException;
use Lemric\BatchProcessing\Job\IdentifyingJobParametersValidator;
use Lemric\BatchProcessing\Launcher\{AsyncJobLauncher, JobLauncherInterface, SimpleJobLauncher};
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Lemric\BatchProcessing\Registry\JobRegistryInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Security\{JobExecutionAccessCheckerInterface, NoOpJobExecutionAccessChecker};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Console wrapper around {@see JobOperatorInterface::start()}.
 *
 * Usage: `bin/console batch:job:launch importOrdersJob --param=date:2025-01-01 --param=run.id:1`
 *
 * Each `--param` option is parsed as `key:value` and converted into the appropriate
 * {@see \Lemric\BatchProcessing\Domain\JobParameter} type using simple heuristics
 * (integer, float, ISO-8601 date, or string fallback).
 *
 * Security: restrict who may run this command (deploy roles, console firewall, CI-only hosts).
 * Launching a job is equivalent to executing its registered business logic with supplied parameters.
 */
#[AsCommand(name: 'batch:job:launch', description: 'Launch a batch job by name.')]
final class LaunchJobCommand extends Command
{
    private readonly JobExecutionAccessCheckerInterface $executionAccessChecker;

    public function __construct(
        private readonly JobOperatorInterface $operator,
        private readonly ?JobRegistryInterface $registry = null,
        private readonly ?JobRepositoryInterface $repository = null,
        private readonly ?SimpleJobLauncher $inlineLauncher = null,
        private readonly ?AsyncJobLauncher $asyncLauncher = null,
        ?JobExecutionAccessCheckerInterface $executionAccessChecker = null,
    ) {
        parent::__construct();
        $this->executionAccessChecker = $executionAccessChecker ?? new NoOpJobExecutionAccessChecker();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('jobName', InputArgument::REQUIRED, 'Registered job name')
            ->addOption('param', 'p', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'key:value job parameter (repeatable)')
            ->addOption('next', null, InputOption::VALUE_NONE, 'Use the configured incrementer to derive the next instance')
            ->addOption('inline', null, InputOption::VALUE_NONE, 'Force in-process synchronous execution (SimpleJobLauncher).')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Force asynchronous execution (AsyncJobLauncher / Messenger).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate parameters and registration without launching the job.')
            ->addOption('restart', null, InputOption::VALUE_REQUIRED, 'Restart the given JobExecution id (shortcut for batch:job:restart).')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Prompt interactively for job parameters (key/value pairs).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $jobName */
        $jobName = $input->getArgument('jobName');

        $restartId = $input->getOption('restart');
        if (null !== $restartId && is_scalar($restartId)) {
            try {
                $priorExecutionId = (int) $restartId;
                $this->executionAccessChecker->assertMayAccessJobExecution($priorExecutionId);
                $id = $this->operator->restart($priorExecutionId);
                $io->success(sprintf('Job "%s" restarted from execution #%s (new executionId=%d).', $jobName, (string) $restartId, $id));

                return self::SUCCESS;
            } catch (JobExecutionAccessDeniedException) {
                $io->error('Access denied.');

                return self::FAILURE;
            } catch (Throwable $e) {
                $io->error('Restart failed.');

                return self::FAILURE;
            }
        }

        if ((bool) $input->getOption('inline') && (bool) $input->getOption('async')) {
            $io->error('Options --inline and --async are mutually exclusive.');

            return self::FAILURE;
        }

        try {
            if (true === $input->getOption('next')) {
                if ((bool) $input->getOption('dry-run')) {
                    $io->success(sprintf('[dry-run] --next would launch the next instance of "%s".', $jobName));

                    return self::SUCCESS;
                }
                $id = $this->operator->startNextInstance($jobName);
            } else {
                /** @var list<string> $rawParams */
                $rawParams = $input->getOption('param');
                $parsed = self::parseParams($rawParams);
                if ((bool) $input->getOption('interactive')) {
                    $parsed = array_merge($parsed, $this->promptParams($io));
                }
                $parameters = JobParameters::of($parsed);

                if ((bool) $input->getOption('dry-run')) {
                    if (null !== $this->repository) {
                        new IdentifyingJobParametersValidator($jobName, $this->repository)->validate($parameters);
                    }
                    $io->success(sprintf('[dry-run] Parameters valid for "%s": %s', $jobName, $parameters->toIdentifyingString()));

                    return self::SUCCESS;
                }

                $launcherOverride = $this->resolveLauncherOverride($input);
                if (null !== $launcherOverride && null !== $this->registry) {
                    $execution = $launcherOverride->run($this->registry->getJob($jobName), $parameters);
                    $id = (int) $execution->getId();
                } else {
                    $id = $this->operator->start($jobName, $parameters);
                }
            }
        } catch (JobExecutionAccessDeniedException) {
            $io->error('Access denied.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $io->error(sprintf('Job "%s" failed to launch.', $jobName));

            return self::FAILURE;
        }

        $io->success(sprintf('Job "%s" launched (executionId=%d).', $jobName, $id));

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

                continue;
            }
            if (preg_match('/^-?\d+\.\d+$/', $value)) {
                $out[$key] = (float) $value;

                continue;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $value)) {
                try {
                    $out[$key] = new DateTimeImmutable($value);

                    continue;
                } catch (Exception) {
                    // fall through to string
                }
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function promptParams(SymfonyStyle $io): array
    {
        $params = [];
        $io->section('Interactive job parameters (leave key empty to finish)');
        while (true) {
            /** @var string|null $rawKey */
            $rawKey = $io->ask('parameter key', '');
            $key = (string) ($rawKey ?? '');
            if ('' === $key) {
                break;
            }
            /** @var string|null $rawValue */
            $rawValue = $io->ask(sprintf('value for "%s"', $key), '');
            $params[$key] = (string) ($rawValue ?? '');
        }

        return $params;
    }

    private function resolveLauncherOverride(InputInterface $input): ?JobLauncherInterface
    {
        if ((bool) $input->getOption('inline')) {
            return $this->inlineLauncher;
        }
        if ((bool) $input->getOption('async')) {
            return $this->asyncLauncher;
        }

        return null;
    }
}
