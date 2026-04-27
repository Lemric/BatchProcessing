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
use Lemric\BatchProcessing\Item\Reader\{ItemReaderAdapter, SynchronizedItemStreamReader};
use Lemric\BatchProcessing\Item\Writer\ItemWriterAdapter;
use PHPUnit\Framework\TestCase;

final class AdapterTest extends TestCase
{
    public function testItemReaderAdapter(): void
    {
        $service = new class {
            private int $counter = 0;

            public function fetchNext(): ?string
            {
                return ++$this->counter <= 3 ? "item{$this->counter}" : null;
            }
        };

        $reader = new ItemReaderAdapter($service, 'fetchNext');

        self::assertSame('item1', $reader->read());
        self::assertSame('item2', $reader->read());
        self::assertSame('item3', $reader->read());
        self::assertNull($reader->read());
    }

    public function testItemWriterAdapter(): void
    {
        $service = new class {
            /** @var list<Chunk<mixed, mixed>> */
            public array $written = [];

            /** @param Chunk<mixed, mixed> $items */
            public function persist(Chunk $items): void
            {
                $this->written[] = $items;
            }
        };

        $writer = new ItemWriterAdapter($service, 'persist');
        $chunk = new Chunk([], ['a', 'b']);
        $writer->write($chunk);

        self::assertCount(1, $service->written);
        self::assertSame(['a', 'b'], $service->written[0]->getOutputItems());
    }

    public function testSynchronizedItemStreamReaderDelegates(): void
    {
        $inner = new class implements \Lemric\BatchProcessing\Item\ItemReaderInterface, \Lemric\BatchProcessing\Item\ItemStreamInterface {
            private int $i = 0;

            public function read(): mixed
            {
                return ++$this->i <= 2 ? $this->i : null;
            }

            public function open(ExecutionContext $ec): void
            {
            }

            public function update(ExecutionContext $ec): void
            {
            }

            public function close(): void
            {
            }
        };

        $reader = new SynchronizedItemStreamReader($inner);
        $ctx = new ExecutionContext();
        $reader->open($ctx);
        self::assertSame(1, $reader->read());
        self::assertSame(2, $reader->read());
        self::assertNull($reader->read());
        $reader->close();
    }
}
