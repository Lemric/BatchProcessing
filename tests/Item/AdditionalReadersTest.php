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
use Lemric\BatchProcessing\Item\ItemReaderInterface;
use Lemric\BatchProcessing\Item\Reader\{JsonLinesItemReader, TransformingItemReader};
use PHPUnit\Framework\TestCase;

final class AdditionalReadersTest extends TestCase
{
    public function testJsonLinesReaderParsesLines(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'jsonl');
        self::assertIsString($tmp);
        file_put_contents($tmp, "{\"id\":1,\"name\":\"A\"}\n{\"id\":2,\"name\":\"B\"}\n");

        try {
            $reader = new JsonLinesItemReader($tmp);
            $ctx = new ExecutionContext();
            $reader->open($ctx);

            $item1 = $reader->read();
            self::assertIsArray($item1);
            self::assertSame(1, $item1['id']);

            $item2 = $reader->read();
            self::assertIsArray($item2);
            self::assertSame('B', $item2['name']);

            self::assertNull($reader->read());

            $reader->close();
        } finally {
            @unlink($tmp);
        }
    }

    public function testTransformingReaderAppliesTransform(): void
    {
        $inner = new class implements ItemReaderInterface {
            private int $index = 0;

            /** @var list<int> */
            private array $data = [1, 2, 3];

            public function read(): mixed
            {
                return $this->data[$this->index++] ?? null;
            }
        };

        /** @var TransformingItemReader<int, int> $reader */
        $reader = new TransformingItemReader($inner, static function (mixed $item): int {
            assert(is_int($item));

            return $item * 10;
        });

        self::assertSame(10, $reader->read());
        self::assertSame(20, $reader->read());
        self::assertSame(30, $reader->read());
        self::assertNull($reader->read());
    }
}
