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

namespace Lemric\BatchProcessing\Step;

use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;

/**
 * Convenience factory producing pre-configured {@see StepBuilder} instances.
 */
final readonly class StepBuilderFactory
{
    public function __construct(
        private JobRepositoryInterface $jobRepository,
        private ?TransactionManagerInterface $transactionManager = null,
    ) {
    }

    public function get(string $name): StepBuilder
    {
        return new StepBuilder($name, $this->jobRepository, $this->transactionManager);
    }
}
