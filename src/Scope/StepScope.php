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

namespace Lemric\BatchProcessing\Scope;

use Lemric\BatchProcessing\Domain\{JobParameters, StepExecution};

/**
 * Lazy-initialization proxy for step-scoped components (readers, writers, processors).
 * The factory callable receives the current {@see StepExecution} and {@see JobParameters}
 * and returns the actual component instance. The component is created only once per step execution.
 *
 * @template T
 *
 * @extends AbstractScope<T>
 */
final class StepScope extends AbstractScope
{
    /**
     * @param callable(StepExecution, JobParameters): T $factory
     */
    public function __construct(
        private $factory,
    ) {
    }

    /**
     * @return T
     */
    public function get(StepExecution $stepExecution): mixed
    {
        $this->ensureActive('StepScope');
        if (null === $this->instance) {
            $this->instance = ($this->factory)(
                $stepExecution,
                $stepExecution->getJobExecution()->getJobParameters(),
            );
        }

        return $this->instance;
    }
}
