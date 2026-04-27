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

namespace Lemric\BatchProcessing\Repository;

use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;

/**
 * {@code BatchConfigurer} parity. Single composition root for the four core
 * services every batch application needs. Implementations are free to autowire defaults
 * (PDO detection, dialect-aware tx isolation, table prefix) — see {@see DefaultBatchConfigurer}.
 */
interface BatchConfigurerInterface
{
    public function getIsolationLevelForCreate(): IsolationLevel;

    public function getJobExplorer(): JobExplorerInterface;

    public function getJobLauncher(): JobLauncherInterface;

    public function getJobRepository(): JobRepositoryInterface;

    public function getTablePrefix(): string;

    public function getTransactionManager(): TransactionManagerInterface;
}
