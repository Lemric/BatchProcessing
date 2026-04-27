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
use Lemric\BatchProcessing\Item\ItemWriterInterface;
use Lemric\BatchProcessing\Item\Processor\{FilteringItemProcessor};
use Lemric\BatchProcessing\Item\Writer\{ClassifierCompositeItemWriter, CompositeItemWriter};
use Lemric\BatchProcessing\Testing\InMemoryItemWriter;
use PHPUnit\Framework\TestCase;

final class AdditionalProcessorsWritersTest extends TestCase
{
    public function testClassifierCompositeItemWriter(): void
    {
        $writerA = new InMemoryItemWriter();
        $writerB = new InMemoryItemWriter();

        $classifier = fn (mixed $item): ItemWriterInterface => $item > 5 ? $writerA : $writerB;

        $writer = new ClassifierCompositeItemWriter($classifier);
        $chunk = new Chunk([3, 7], [3, 7]);
        $writer->write($chunk);

        self::assertSame([7], $writerA->getWrittenItems());
        self::assertSame([3], $writerB->getWrittenItems());
    }

    public function testCompositeItemWriterDelegatesToAll(): void
    {
        $writer1 = new InMemoryItemWriter();
        $writer2 = new InMemoryItemWriter();

        $composite = new CompositeItemWriter([$writer1, $writer2]);
        $chunk = new Chunk([1, 2], [1, 2]);
        $composite->write($chunk);

        self::assertSame([1, 2], $writer1->getWrittenItems());
        self::assertSame([1, 2], $writer2->getWrittenItems());
    }

    public function testFilteringProcessorFiltersItems(): void
    {
        $processor = new FilteringItemProcessor(fn (int $item) => $item > 5);

        self::assertSame(10, $processor->process(10));
        self::assertNull($processor->process(3));
    }
}
