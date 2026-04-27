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

use Lemric\BatchProcessing\Explorer\{JobExplorerInterface, SimpleJobExplorer};
use Lemric\BatchProcessing\Launcher\{JobLauncherInterface, SimpleJobLauncher};
use Lemric\BatchProcessing\Transaction\{PdoTransactionManager, ResourcelessTransactionManager, TransactionManagerInterface};
use PDO;

/**
 * Reference {@see BatchConfigurerInterface}: derives sensible defaults from a single
 * {@see PDO} connection. PDO-bound services are constructed lazily.
 */
final class DefaultBatchConfigurer implements BatchConfigurerInterface
{
    private ?JobExplorerInterface $explorer = null;

    private ?JobLauncherInterface $launcher = null;

    private ?PdoJobRepository $repository = null;

    private ?TransactionManagerInterface $transactionManager = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tablePrefix = 'batch_',
        private readonly IsolationLevel $isolationLevelForCreate = IsolationLevel::SERIALIZABLE,
    ) {
    }

    public function getIsolationLevelForCreate(): IsolationLevel
    {
        return $this->isolationLevelForCreate;
    }

    public function getJobExplorer(): JobExplorerInterface
    {
        return $this->explorer ??= new SimpleJobExplorer($this->getJobRepository());
    }

    public function getJobLauncher(): JobLauncherInterface
    {
        return $this->launcher ??= new SimpleJobLauncher($this->getJobRepository());
    }

    public function getJobRepository(): JobRepositoryInterface
    {
        return $this->repository ??= new PdoJobRepository(
            $this->pdo,
            $this->tablePrefix,
            $this->isolationLevelForCreate,
        );
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function getTransactionManager(): TransactionManagerInterface
    {
        return $this->transactionManager ??= class_exists(PdoTransactionManager::class)
            ? new PdoTransactionManager($this->pdo)
            : new ResourcelessTransactionManager();
    }
}
