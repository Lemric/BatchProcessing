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

namespace Lemric\BatchProcessing\Tests\Integration;

use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Domain\{BatchStatus, JobParameters};
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemWriterInterface};
use Lemric\BatchProcessing\Job\JobBuilderFactory;
use Lemric\BatchProcessing\Launcher\SimpleJobLauncher;
use Lemric\BatchProcessing\Repository\{PdoJobRepository, PdoJobRepositorySchema};
use Lemric\BatchProcessing\Step\StepBuilderFactory;
use Lemric\BatchProcessing\Transaction\PdoTransactionManager;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * End-to-end integration test simulating FAIL → restart with checkpoint using
 * PdoJobRepository + SQLite in-memory. Validates the full restart lifecycle as
 * described in spec §18.
 */
final class RestartCheckpointIntegrationTest extends TestCase
{
    private PDO $pdo;

    private PdoJobRepository $repository;

    private PdoTransactionManager $txManager;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach (PdoJobRepositorySchema::sqlForPlatform('sqlite') as $sql) {
            $this->pdo->exec($sql);
        }

        $this->repository = new PdoJobRepository($this->pdo);
        $this->txManager = new PdoTransactionManager($this->pdo);
    }

    public function testRestartFromCheckpointAfterFailure(): void
    {
        /** @var list<string> $items */
        $items = ['A', 'B', 'C', 'D', 'E'];
        /** @var list<string> $writtenItems */
        $writtenItems = [];
        $failOnItem = 'C';

        $reader = new class($items) implements ItemReaderInterface {
            private int $index = 0;

            /** @param list<string> $items */
            public function __construct(private readonly array $items)
            {
            }

            public function read(): mixed
            {
                return $this->items[$this->index++] ?? null;
            }

            public function reset(): void
            {
                $this->index = 0;
            }
        };

        $writer = new class($writtenItems, $failOnItem) implements ItemWriterInterface {
            public bool $shouldFail = true;

            /**
             * @param list<string> $written
             */
            public function __construct(
                private array &$written,
                private readonly string $failOnItem,
            ) {
            }

            /**
             * @return list<string>
             */
            public function getWritten(): array
            {
                return $this->written;
            }

            public function write(Chunk $items): void
            {
                foreach ($items->getOutputItems() as $item) {
                    assert(is_string($item));
                    if ($this->shouldFail && $item === $this->failOnItem) {
                        throw new RuntimeException("Cannot write item: {$item}");
                    }
                    $this->written[] = $item;
                }
            }
        };

        $stepFactory = new StepBuilderFactory($this->repository, $this->txManager);
        $jobFactory = new JobBuilderFactory($this->repository);

        // Use chunk size 1 so each item has its own transaction.
        $step = $stepFactory->get('importStep')
            ->chunk(1, $reader, null, $writer)
            ->build();

        $job = $jobFactory->get('importJob')
            ->start($step)
            ->build();

        $launcher = new SimpleJobLauncher($this->repository);

        // First run — should fail when writing 'C'.
        $params = JobParameters::of(['run.id' => 1]);

        try {
            $launcher->run($job, $params);
        } catch (RuntimeException) {
            // Expected.
        }

        // Verify partial writes happened.
        self::assertContains('A', $writtenItems);
        self::assertContains('B', $writtenItems);
        self::assertNotContains('C', $writtenItems);

        // Fix the problem.
        $writer->shouldFail = false;
        $reader->reset();

        // Second run — restart. Should complete.
        $execution2 = $launcher->run($job, $params);
        self::assertSame(BatchStatus::COMPLETED, $execution2->getStatus());
    }
}
