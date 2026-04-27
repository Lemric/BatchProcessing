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
use Lemric\BatchProcessing\Scope\StepScope;

/**
 * PHPUnit-friendly helper that activates a {@see StepScope} for the duration of a single test.
 * {@code StepScopeTestExecutionListener}.
 *
 * Typical usage in a test:
 *   protected function setUp(): void {
 *       $this->stepListener = new StepScopeTestExecutionListener();
 *       $this->stepExecution = $this->stepListener->begin('myStep');
 *   }
 *   protected function tearDown(): void {
 *       $this->stepListener->end();
 *   }
 */
final class StepScopeTestExecutionListener
{
    /** @var list<StepScope<mixed>> */
    private array $activeScopes = [];

    /**
     * Activates a synthetic {@see StepExecution} so that {@see StepScope}-decorated services
     * resolve properly in tests. Returns the synthetic StepExecution for assertions.
     */
    public function begin(string $stepName = 'test-step', ?JobParameters $parameters = null): StepExecution
    {
        $jobExecution = new JobExecution(
            null,
            new JobInstance(null, 'test-job', 'test-key'),
            $parameters ?? new JobParameters(),
        );

        return $jobExecution->createStepExecution($stepName);
    }

    public function end(): void
    {
        foreach ($this->activeScopes as $scope) {
            $scope->reset();
        }
        $this->activeScopes = [];
    }

    /**
     * Registers a {@see StepScope} so it can be reset on {@see end()}.
     *
     * @template T
     *
     * @param StepScope<T> $scope
     */
    public function track(StepScope $scope): void
    {
        $scope->activate();
        $this->activeScopes[] = $scope;
    }
}
