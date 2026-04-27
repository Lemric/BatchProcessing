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

namespace Lemric\BatchProcessing\Testing;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters, StepExecution};

/**
 * Convenience factory for creating domain objects in unit tests without going through the
 * full repository lifecycle. All objects are created with sensible defaults.
 */
final class MetaDataInstanceFactory
{
    private static int $sequence = 0;

    /**
     * Creates a {@see JobExecution} with a linked {@see JobInstance}.
     */
    public static function createJobExecution(
        string $jobName = 'testJob',
        ?int $executionId = null,
        ?JobParameters $parameters = null,
    ): JobExecution {
        $instance = self::createJobInstance($jobName);

        return new JobExecution(
            $executionId ?? ++self::$sequence,
            $instance,
            $parameters ?? new JobParameters([]),
        );
    }

    /**
     * Creates a minimal {@see JobInstance} with an auto-generated id.
     */
    public static function createJobInstance(string $jobName = 'testJob', ?int $id = null): JobInstance
    {
        return new JobInstance($id ?? ++self::$sequence, $jobName, hash('sha3-256', $jobName));
    }

    /**
     * Creates a {@see StepExecution} with a linked {@see JobExecution}.
     */
    public static function createStepExecution(
        string $stepName = 'testStep',
        ?JobExecution $jobExecution = null,
        ?int $id = null,
    ): StepExecution {
        $jobExecution ??= self::createJobExecution();

        return new StepExecution($stepName, $jobExecution, $id ?? ++self::$sequence);
    }

    /**
     * Resets the internal sequence counter. Useful in setUp() methods to get deterministic ids.
     */
    public static function resetSequence(): void
    {
        self::$sequence = 0;
    }
}
