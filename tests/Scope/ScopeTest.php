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

namespace Lemric\BatchProcessing\Tests\Scope;

use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters, StepExecution};
use Lemric\BatchProcessing\Scope\{JobScope, StepScope};
use PHPUnit\Framework\TestCase;
use stdClass;

final class ScopeTest extends TestCase
{
    public function testJobScopeLazyCreation(): void
    {
        $scope = new JobScope(function (JobExecution $je, JobParameters $jp) {
            return 'writer:'.$jp->getString('output', 'default');
        });
        $scope->activate();

        $jobExecution = new JobExecution(
            null,
            new JobInstance(null, 'j', 'j'),
            JobParameters::of(['output' => '/data/out']),
        );

        $instance = $scope->get($jobExecution);
        self::assertSame('writer:/data/out', $instance);
        self::assertSame($instance, $scope->get($jobExecution)); // same
    }

    public function testJobScopeReset(): void
    {
        $scope = new JobScope(fn () => new stdClass());
        $scope->activate();
        $jobExecution = new JobExecution(null, new JobInstance(null, 'j', 'j'), new JobParameters());

        $obj1 = $scope->get($jobExecution);
        $scope->reset();
        $scope->activate();
        $obj2 = $scope->get($jobExecution);
        self::assertNotSame($obj1, $obj2);
    }

    public function testStepScopeLazyCreation(): void
    {
        $callCount = 0;
        $scope = new StepScope(function (StepExecution $se, JobParameters $jp) use (&$callCount) {
            ++$callCount;

            return 'reader:'.$jp->getString('file', 'default');
        });
        $scope->activate();

        $jobExecution = new JobExecution(null, new JobInstance(null, 'j', 'j'), JobParameters::of(['file' => '/data/in.csv']));
        $stepExecution = new StepExecution('step', $jobExecution);

        $instance1 = $scope->get($stepExecution);
        $instance2 = $scope->get($stepExecution);

        self::assertSame('reader:/data/in.csv', $instance1);
        self::assertSame($instance1, $instance2); // same instance
        self::assertSame(1, $callCount); // factory called only once
    }

    public function testStepScopeReset(): void
    {
        $scope = new StepScope(fn () => new stdClass());
        $scope->activate();
        $jobExecution = new JobExecution(null, new JobInstance(null, 'j', 'j'), new JobParameters());
        $stepExecution = new StepExecution('s', $jobExecution);

        $obj1 = $scope->get($stepExecution);
        $scope->reset();
        $scope->activate();
        $obj2 = $scope->get($stepExecution);

        self::assertNotSame($obj1, $obj2);
    }
}
