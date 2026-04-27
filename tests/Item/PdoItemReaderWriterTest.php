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

namespace Lemric\BatchProcessing\Tests\Item;

use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\Reader\PdoItemReader;
use Lemric\BatchProcessing\Item\Writer\PdoItemWriter;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoItemReaderWriterTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, value REAL)');
    }

    public function testPdoItemReaderReadsRows(): void
    {
        $this->pdo->exec("INSERT INTO items (name, value) VALUES ('A', 1.0)");
        $this->pdo->exec("INSERT INTO items (name, value) VALUES ('B', 2.0)");
        $this->pdo->exec("INSERT INTO items (name, value) VALUES ('C', 3.0)");

        $reader = new PdoItemReader($this->pdo, 'SELECT name, value FROM items ORDER BY id');
        $reader->open(new ExecutionContext());

        $items = [];
        while (null !== ($item = $reader->read())) {
            self::assertIsArray($item);
            $items[] = $item;
        }

        self::assertCount(3, $items);
        self::assertSame('A', $items[0]['name']);
        self::assertSame('C', $items[2]['name']);

        $reader->close();
    }

    public function testPdoItemReaderResumesFromCheckpoint(): void
    {
        $this->pdo->exec("INSERT INTO items (name, value) VALUES ('A', 1.0)");
        $this->pdo->exec("INSERT INTO items (name, value) VALUES ('B', 2.0)");
        $this->pdo->exec("INSERT INTO items (name, value) VALUES ('C', 3.0)");

        $reader = new PdoItemReader($this->pdo, 'SELECT name, value FROM items ORDER BY id');

        $ctx = new ExecutionContext();
        $reader->open($ctx);
        $reader->read();
        $reader->read();
        $reader->update($ctx);
        $reader->close();

        $reader2 = new PdoItemReader($this->pdo, 'SELECT name, value FROM items ORDER BY id');
        $reader2->open($ctx);
        $item = $reader2->read();
        self::assertIsArray($item);
        self::assertSame('C', $item['name']);
        self::assertNull($reader2->read());
        $reader2->close();
    }

    public function testPdoItemReaderWithRowMapper(): void
    {
        $this->pdo->exec("INSERT INTO items (name, value) VALUES ('Test', 42.0)");

        $reader = new PdoItemReader(
            $this->pdo,
            'SELECT name, value FROM items',
            rowMapper: static function (mixed $row): string {
                self::assertIsArray($row);
                $name = $row['name'];
                $value = $row['value'];
                self::assertIsString($name);

                return $name.':'.(is_numeric($value) ? (string) $value : '');
            },
        );
        $reader->open(new ExecutionContext());
        self::assertSame('Test:42', $reader->read());
        $reader->close();
    }

    public function testPdoItemWriterInsertsRows(): void
    {
        $writer = new PdoItemWriter(
            $this->pdo,
            'INSERT INTO items (name, value) VALUES (:name, :value)',
            /**
             * @param array{name: string, value: float} $item
             *
             * @return array<string, scalar|null>
             */
            static function (array $item): array {
                /** @var array<string, scalar|null> $params */
                $params = [':name' => $item['name'], ':value' => $item['value']];

                return $params;
            },
        );

        $writer->open(new ExecutionContext());
        $chunk = new Chunk([], [
            ['name' => 'Alpha', 'value' => 1.1],
            ['name' => 'Beta', 'value' => 2.2],
        ]);
        $writer->write($chunk);

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM items');
        self::assertNotFalse($stmt);
        self::assertSame(2, (int) $stmt->fetchColumn());

        $writer->close();
    }
}
