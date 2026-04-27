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

use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\Reader\JsonItemReader;
use PHPUnit\Framework\TestCase;

final class JsonItemReaderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'json_reader_');
        self::assertNotFalse($tmp);
        $this->tempFile = $tmp;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testEmptyArray(): void
    {
        file_put_contents($this->tempFile, '[]');

        $reader = new JsonItemReader($this->tempFile);
        $reader->open(new ExecutionContext());

        self::assertNull($reader->read());
        $reader->close();
    }

    public function testReadsJsonArray(): void
    {
        file_put_contents($this->tempFile, '[{"name":"Alice"},{"name":"Bob"},{"name":"Charlie"}]');

        $reader = new JsonItemReader($this->tempFile);
        $reader->open(new ExecutionContext());

        /** @var list<array{name: string}> $items */
        $items = [];
        while (null !== ($item = $reader->read())) {
            self::assertIsArray($item);
            $items[] = $item;
        }

        self::assertCount(3, $items);
        self::assertSame('Alice', $items[0]['name']);
        self::assertSame('Bob', $items[1]['name']);
        self::assertSame('Charlie', $items[2]['name']);

        $reader->close();
    }

    public function testReadsWithMapper(): void
    {
        file_put_contents($this->tempFile, '[{"id":1},{"id":2}]');

        $reader = new JsonItemReader(
            $this->tempFile,
            mapper: static function (mixed $row): int {
                self::assertIsArray($row);
                self::assertIsInt($row['id']);

                return $row['id'] * 10;
            },
        );
        $reader->open(new ExecutionContext());

        self::assertSame(10, $reader->read());
        self::assertSame(20, $reader->read());
        self::assertNull($reader->read());

        $reader->close();
    }

    public function testResumeFromCheckpoint(): void
    {
        file_put_contents($this->tempFile, '[{"id":1},{"id":2},{"id":3}]');

        $reader = new JsonItemReader($this->tempFile);
        $ctx = new ExecutionContext();
        $reader->open($ctx);
        $reader->read(); // 1
        $reader->read(); // 2
        $reader->update($ctx);
        $reader->close();

        $reader2 = new JsonItemReader($this->tempFile);
        $reader2->open($ctx);
        $item = $reader2->read();
        self::assertIsArray($item);
        self::assertSame(3, $item['id']);
        self::assertNull($reader2->read());
        $reader2->close();
    }
}
