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

use Lemric\BatchProcessing\Exception\{NonTransientResourceException, ParseException};
use Lemric\BatchProcessing\Item\Reader\CsvItemReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CsvItemReaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/batch_csv_test_'.uniqid();
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir.'/*');
        if (false !== $files) {
            array_map('unlink', $files);
        }
        @rmdir($this->tmpDir);
    }

    public function testLinesToSkipSkipsHeader(): void
    {
        $path = $this->tmpDir.'/header.csv';
        file_put_contents($path, "header\ndata\n");

        $reader = new CsvItemReader(
            filePath: $path,
            fieldSetMapper: static fn (array $row): string => $row[0],
            linesToSkip: 1,
        );

        self::assertSame('data', $reader->read());
        self::assertNull($reader->read());
    }

    public function testNonStrictModeReturnsNullOnParseError(): void
    {
        $path = $this->tmpDir.'/bad2.csv';
        file_put_contents($path, "a,b\n1,2\n");

        $reader = new CsvItemReader(
            filePath: $path,
            fieldSetMapper: static function (array $row): never {
                throw new RuntimeException('parse error');
            },
            strict: false,
        );

        $rows = [];
        for ($i = 0; $i < 3; ++$i) {
            $rows[] = $reader->read();
        }
        self::assertSame([null, null, null], $rows);
    }

    public function testReadsCsvFile(): void
    {
        $path = $this->tmpDir.'/data.csv';
        file_put_contents($path, "name,age\nAlice,30\nBob,25\n");

        $reader = new CsvItemReader(
            filePath: $path,
            fieldSetMapper: static fn (array $row, int $line): array => ['name' => $row[0], 'age' => (int) $row[1]],
            linesToSkip: 1,
        );

        $item1 = $reader->read();
        self::assertSame(['name' => 'Alice', 'age' => 30], $item1);

        $item2 = $reader->read();
        self::assertSame(['name' => 'Bob', 'age' => 25], $item2);

        self::assertNull($reader->read());
    }

    public function testReadsCsvWithCustomDelimiter(): void
    {
        $path = $this->tmpDir.'/tabs.csv';
        file_put_contents($path, "a\tb\n1\t2\n");

        $reader = new CsvItemReader(
            filePath: $path,
            fieldSetMapper: static fn (array $row): array => $row,
            delimiter: "\t",
            linesToSkip: 1,
        );

        self::assertSame(['1', '2'], $reader->read());
        self::assertNull($reader->read());
    }

    public function testStrictModeThrowsParseException(): void
    {
        $path = $this->tmpDir.'/bad.csv';
        file_put_contents($path, "a,b\n1,2\n");

        $reader = new CsvItemReader(
            filePath: $path,
            fieldSetMapper: static function (array $row): never {
                throw new RuntimeException('parse error');
            },
            strict: true,
        );

        $this->expectException(ParseException::class);
        $reader->read();
    }

    public function testThrowsOnNonExistentFile(): void
    {
        $reader = new CsvItemReader(
            filePath: '/nonexistent/file.csv',
            fieldSetMapper: static fn (array $row): array => $row,
        );

        $this->expectException(NonTransientResourceException::class);
        $reader->read();
    }
}
