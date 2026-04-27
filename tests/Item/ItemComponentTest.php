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
use Lemric\BatchProcessing\Item\Processor\{CompositeItemProcessor, FilteringItemProcessor, PassThroughItemProcessor};
use Lemric\BatchProcessing\Item\Reader\{CallbackItemReader, IteratorItemReader, TransformingItemReader};
use Lemric\BatchProcessing\Item\Writer\{CallbackItemWriter, CompositeItemWriter};
use Lemric\BatchProcessing\Testing\InMemoryItemWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ItemComponentTest extends TestCase
{
    // ── Readers ─────────────────────────────────────────────────────────

    public function testCallbackItemReaderDelegatesToCallback(): void
    {
        $items = [1, 2, 3];
        $idx = 0;
        $reader = new CallbackItemReader(static function () use (&$items, &$idx): mixed {
            return $items[$idx++] ?? null;
        });
        self::assertSame(1, $reader->read());
        self::assertSame(2, $reader->read());
        self::assertSame(3, $reader->read());
        self::assertNull($reader->read());
    }

    // ── Writers ─────────────────────────────────────────────────────────

    public function testCallbackItemWriterDelegatesToCallback(): void
    {
        $written = [];
        $writer = new CallbackItemWriter(static function (array $items) use (&$written): void {
            $written = array_merge($written, $items);
        });
        $writer->write(new Chunk([], [1, 2, 3]));
        self::assertSame([1, 2, 3], $written);
    }

    public function testCompositeItemProcessorChainsDelegates(): void
    {
        /** @var list<\Lemric\BatchProcessing\Item\ItemProcessorInterface<mixed, mixed>> $delegates */
        $delegates = [
            new FilteringItemProcessor(static fn (int $i): bool => $i > 0),
            new PassThroughItemProcessor(),
        ];
        $p = new CompositeItemProcessor($delegates);
        self::assertSame(5, $p->process(5));
        self::assertNull($p->process(-1));
    }

    public function testCompositeItemProcessorRejectsEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CompositeItemProcessor([]);
    }

    public function testCompositeItemProcessorShortCircuitsOnNull(): void
    {
        $secondCalled = false;
        $p = new CompositeItemProcessor([
            new FilteringItemProcessor(static fn (): bool => false),
            new class($secondCalled) implements \Lemric\BatchProcessing\Item\ItemProcessorInterface {
                /** @phpstan-ignore property.onlyWritten */
                public function __construct(private bool &$called)
                {
                }

                public function process(mixed $item): mixed
                {
                    $this->called = true;

                    return $item;
                }
            },
        ]);
        $p->process('x');
        self::assertFalse($secondCalled);
    }

    public function testCompositeItemWriterFansOutToAllDelegates(): void
    {
        $w1 = new InMemoryItemWriter();
        $w2 = new InMemoryItemWriter();
        $composite = new CompositeItemWriter([$w1, $w2]);

        $composite->write(new Chunk([], ['a', 'b']));

        self::assertSame(['a', 'b'], $w1->getWrittenItems());
        self::assertSame(['a', 'b'], $w2->getWrittenItems());
    }

    public function testCompositeItemWriterRejectsEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CompositeItemWriter([]);
    }

    public function testFilteringProcessorFiltersCorrectly(): void
    {
        $p = new FilteringItemProcessor(static fn (int $i): bool => $i > 3);
        self::assertSame(5, $p->process(5));
        self::assertNull($p->process(2));
    }

    public function testInMemoryItemWriterDisableFailures(): void
    {
        $writer = new InMemoryItemWriter(failOnInvocation: 1);
        $writer->disableFailures();
        $writer->write(new Chunk([], [1])); // should not throw
        self::assertSame([1], $writer->getWrittenItems());
    }

    // ── InMemoryItemWriter ──────────────────────────────────────────────

    public function testInMemoryItemWriterFailsOnConfiguredInvocation(): void
    {
        $writer = new InMemoryItemWriter(failOnInvocation: 2);
        $writer->write(new Chunk([], [1])); // invocation 1 - ok
        $this->expectException(RuntimeException::class);
        $writer->write(new Chunk([], [2])); // invocation 2 - fails
    }

    public function testInMemoryItemWriterReset(): void
    {
        $writer = new InMemoryItemWriter();
        $writer->write(new Chunk([], [1, 2]));
        self::assertSame([1, 2], $writer->getWrittenItems());
        self::assertSame(1, $writer->getWriteCount());

        $writer->reset();
        self::assertSame([], $writer->getWrittenItems());
        self::assertSame(0, $writer->getWriteCount());
    }

    public function testIteratorItemReaderReadsFromArray(): void
    {
        $reader = new IteratorItemReader(['a', 'b', 'c']);
        self::assertSame('a', $reader->read());
        self::assertSame('b', $reader->read());
        self::assertSame('c', $reader->read());
        self::assertNull($reader->read());
    }

    public function testIteratorItemReaderReadsFromGenerator(): void
    {
        $gen = static function () {
            yield 10;
            yield 20;
        };
        $reader = new IteratorItemReader($gen());
        self::assertSame(10, $reader->read());
        self::assertSame(20, $reader->read());
        self::assertNull($reader->read());
    }
    // ── Processors ──────────────────────────────────────────────────────

    public function testPassThroughProcessorReturnsInput(): void
    {
        $p = new PassThroughItemProcessor();
        self::assertSame('hello', $p->process('hello'));
        self::assertSame(42, $p->process(42));
    }

    public function testTransformingItemReaderAppliesTransformation(): void
    {
        $delegate = new CallbackItemReader(static function (): mixed {
            /** @var int $i */
            static $i = 0;

            return ++$i <= 3 ? $i : null;
        });
        $reader = new TransformingItemReader($delegate, static fn (int|float $n): int => (int) ($n * 10));
        self::assertSame(10, $reader->read());
        self::assertSame(20, $reader->read());
        self::assertSame(30, $reader->read());
        self::assertNull($reader->read());
    }
}
