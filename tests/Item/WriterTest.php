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
use Lemric\BatchProcessing\Item\FlatFile\{DelimitedLineAggregator, PassThroughFieldExtractor, PassThroughLineAggregator};
use Lemric\BatchProcessing\Item\Writer\{FlatFileItemWriter, JsonFileItemWriter};
use PHPUnit\Framework\TestCase;

use const PHP_EOL;

final class WriterTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'writer_test_');
        self::assertNotFalse($tmp);
        $this->tempFile = $tmp;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testFlatFileItemWriterCsvOutput(): void
    {
        $agg = new DelimitedLineAggregator(new PassThroughFieldExtractor(), ',');
        $writer = new FlatFileItemWriter(
            $this->tempFile,
            $agg,
            headerCallback: fn () => 'name,age',
        );

        $writer->open(new ExecutionContext());
        $writer->write(new Chunk([], [['Alice', 30], ['Bob', 25]]));
        $writer->close();

        $lines = array_values(array_filter(explode(PHP_EOL, $this->readTempFile())));
        self::assertSame('name,age', $lines[0]);
        self::assertSame('Alice,30', $lines[1]);
        self::assertSame('Bob,25', $lines[2]);
    }

    public function testFlatFileItemWriterWithHeaderAndFooter(): void
    {
        $agg = new PassThroughLineAggregator();
        $writer = new FlatFileItemWriter(
            $this->tempFile,
            $agg,
            headerCallback: fn () => 'HEADER',
            footerCallback: fn () => 'FOOTER',
        );

        $writer->open(new ExecutionContext());
        $writer->write(new Chunk([], ['data']));
        $writer->close();

        $content = $this->readTempFile();
        self::assertStringContainsString('HEADER', $content);
        self::assertStringContainsString('data', $content);
        self::assertStringContainsString('FOOTER', $content);
    }

    public function testFlatFileItemWriterWritesLines(): void
    {
        $agg = new PassThroughLineAggregator();
        $writer = new FlatFileItemWriter($this->tempFile, $agg);

        $writer->open(new ExecutionContext());
        $writer->write(new Chunk([], ['line1', 'line2', 'line3']));
        $writer->close();

        $content = $this->readTempFile();
        self::assertStringContainsString('line1', $content);
        self::assertStringContainsString('line2', $content);
        self::assertStringContainsString('line3', $content);
    }

    public function testJsonFileItemWriterMultipleChunks(): void
    {
        $writer = new JsonFileItemWriter($this->tempFile);

        $writer->open(new ExecutionContext());
        $writer->write(new Chunk([], [['id' => 1]]));
        $writer->write(new Chunk([], [['id' => 2]]));
        $writer->close();

        $decoded = json_decode($this->readTempFile(), true);
        self::assertIsArray($decoded);
        self::assertCount(2, $decoded);
        self::assertIsArray($decoded[0]);
        self::assertIsArray($decoded[1]);
        self::assertSame(1, $decoded[0]['id']);
        self::assertSame(2, $decoded[1]['id']);
    }

    public function testJsonFileItemWriterWritesArray(): void
    {
        $writer = new JsonFileItemWriter($this->tempFile);

        $writer->open(new ExecutionContext());
        $writer->write(new Chunk([], [['name' => 'Alice'], ['name' => 'Bob']]));
        $writer->close();

        $decoded = json_decode($this->readTempFile(), true);
        self::assertIsArray($decoded);
        self::assertCount(2, $decoded);
        self::assertIsArray($decoded[0]);
        self::assertIsArray($decoded[1]);
        self::assertSame('Alice', $decoded[0]['name']);
        self::assertSame('Bob', $decoded[1]['name']);
    }

    private function readTempFile(): string
    {
        $content = file_get_contents($this->tempFile);
        self::assertNotFalse($content);

        return $content;
    }
}
