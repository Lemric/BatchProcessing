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

use InvalidArgumentException;
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\Processor\ValidatingItemProcessor;
use Lemric\BatchProcessing\Item\Reader\JsonLinesItemReader;
use Lemric\BatchProcessing\Item\Writer\ClassifierCompositeItemWriter;
use Lemric\BatchProcessing\Testing\InMemoryItemWriter;
use PHPUnit\Framework\TestCase;

final class NewItemComponentTest extends TestCase
{
    // ── ClassifierCompositeItemWriter ───────────────────────────────────

    public function testClassifierCompositeItemWriterRoutesItems(): void
    {
        $writerA = new InMemoryItemWriter();
        $writerB = new InMemoryItemWriter();

        /** @var ClassifierCompositeItemWriter<string> $writer */
        $writer = new ClassifierCompositeItemWriter(
            static function (mixed $item) use ($writerA, $writerB): \Lemric\BatchProcessing\Item\ItemWriterInterface {
                self::assertIsString($item);

                return str_starts_with($item, 'A') ? $writerA : $writerB;
            },
        );

        $writer->write(new Chunk([], ['A1', 'B1', 'A2', 'B2']));

        self::assertSame(['A1', 'A2'], $writerA->getWrittenItems());
        self::assertSame(['B1', 'B2'], $writerB->getWrittenItems());
    }

    // ── JsonLinesItemReader ─────────────────────────────────────────────

    public function testJsonLinesReaderReadsLines(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'jsonl_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "{\"id\":1}\n{\"id\":2}\n{\"id\":3}\n");

        try {
            $reader = new JsonLinesItemReader($tmpFile);
            /** @var array{id: int} $item1 */
            $item1 = $reader->read();
            self::assertSame(1, $item1['id']);
            /** @var array{id: int} $item2 */
            $item2 = $reader->read();
            self::assertSame(2, $item2['id']);
            /** @var array{id: int} $item3 */
            $item3 = $reader->read();
            self::assertSame(3, $item3['id']);
            self::assertNull($reader->read());
        } finally {
            unlink($tmpFile);
        }
    }

    public function testJsonLinesReaderSkipsBlankLines(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'jsonl_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "{\"a\":1}\n\n\n{\"a\":2}\n");

        try {
            $reader = new JsonLinesItemReader($tmpFile);
            $reader->read();
            /** @var array{a: int} $item */
            $item = $reader->read();
            self::assertSame(2, $item['a']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testJsonLinesReaderWithMapper(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'jsonl_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "{\"val\":42}\n");

        try {
            $reader = new JsonLinesItemReader($tmpFile, mapper: static function (mixed $row): int {
                assert(is_array($row));
                /** @var int $val */
                $val = $row['val'];

                return $val * 2;
            });
            self::assertSame(84, $reader->read());
        } finally {
            unlink($tmpFile);
        }
    }

    public function testValidatingProcessorFiltersWhenConfigured(): void
    {
        $p = new ValidatingItemProcessor(
            static fn (int $i): bool => $i > 0,
            filter: true,
        );
        self::assertNull($p->process(-1));
        self::assertSame(5, $p->process(5));
    }
    // ── ValidatingItemProcessor ─────────────────────────────────────────

    public function testValidatingProcessorPassesValidItems(): void
    {
        $p = new ValidatingItemProcessor(static fn (int $i): bool => $i > 0);
        self::assertSame(5, $p->process(5));
    }

    public function testValidatingProcessorThrowsOnInvalidItem(): void
    {
        $p = new ValidatingItemProcessor(
            static fn (int $i): bool => $i > 0,
            message: 'Must be positive',
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Must be positive');
        $p->process(-1);
    }
}
