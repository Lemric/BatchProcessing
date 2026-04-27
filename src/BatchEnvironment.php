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

use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Lemric\BatchProcessing\Registry\JobRegistryInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\StepBuilderFactory;
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;

/**
 * Immutable value object that holds all wired components of a batch-processing environment.
 *
 * Replaces the previously used associative array, providing type-safe access,
 * IDE autocompletion and enforced immutability.
 */
final readonly class BatchEnvironment
{
    public function __construct(
        public JobRepositoryInterface $repository,
        public TransactionManagerInterface $transactionManager,
        public StepBuilderFactory $stepBuilderFactory,
        public JobBuilderFactory $jobBuilderFactory,
        public JobLauncherInterface $launcher,
        public JobRegistryInterface $registry,
        public JobOperatorInterface $operator,
        public JobExplorerInterface $explorer,
    ) {
    }

    /**
     * Creates a pre-populated builder so the current environment can be adjusted
     * without re-wiring every dependency from scratch.
     */
    public function toBuilder(): BatchEnvironmentBuilder
    {
        return BatchEnvironmentBuilder::create()
            ->withRepository($this->repository)
            ->withTransactionManager($this->transactionManager)
            ->withStepBuilderFactory($this->stepBuilderFactory)
            ->withJobBuilderFactory($this->jobBuilderFactory)
            ->withLauncher($this->launcher)
            ->withRegistry($this->registry)
            ->withOperator($this->operator)
            ->withExplorer($this->explorer);
    }
}
