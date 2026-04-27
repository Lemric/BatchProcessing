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

namespace Lemric\BatchProcessing;

use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Job\JobBuilder;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\StepBuilder;
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;
use PDO;

/**
 * Static facade providing the most common entry points to the framework.
 *
 * Use {@see builder()} to obtain a fully configurable {@see BatchEnvironmentBuilder},
 * or the convenience shortcuts {@see job()} / {@see step()} for ad-hoc building.
 *
 * In production, prefer wiring the building blocks through your DI container
 * or use {@see BatchEnvironmentBuilder} directly for full control.
 */
final class BatchProcessing
{
    /**
     * @param callable(int, string, JobParameters): void $dispatcher
     */
    public static function asyncEnvironment(
        callable $dispatcher,
        ?JobRepositoryInterface $repository = null,
        ?TransactionManagerInterface $transactionManager = null,
    ): BatchEnvironment {
        return BatchEnvironmentBuilder::async($dispatcher, $repository, $transactionManager)->build();
    }

    /**
     * Returns a fresh {@see BatchEnvironmentBuilder} for full configuration.
     */
    public static function builder(): BatchEnvironmentBuilder
    {
        return BatchEnvironmentBuilder::create();
    }

    /**
     * Builds a configured environment.
     *
     * @param callable(BatchEnvironmentBuilder): BatchEnvironmentBuilder|null $configure
     */
    public static function environment(?callable $configure = null): BatchEnvironment
    {
        $builder = self::builder();
        if (null !== $configure) {
            $builder = $configure($builder);
        }

        return $builder->build();
    }

    public static function inMemoryEnvironment(): BatchEnvironment
    {
        return BatchEnvironmentBuilder::inMemory()->build();
    }

    public static function job(string $name, JobRepositoryInterface $repo): JobBuilder
    {
        return new JobBuilder($name, $repo);
    }

    public static function pdoEnvironment(PDO $pdo, string $tablePrefix = 'batch_'): BatchEnvironment
    {
        return BatchEnvironmentBuilder::pdo($pdo, $tablePrefix)->build();
    }

    public static function step(string $name, JobRepositoryInterface $repo, ?TransactionManagerInterface $tx = null): StepBuilder
    {
        return new StepBuilder($name, $repo, $tx);
    }
}
