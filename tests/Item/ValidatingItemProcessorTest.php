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
use Lemric\BatchProcessing\Item\Processor\ValidatingItemProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ValidatingItemProcessorTest extends TestCase
{
    public function testFilterModeReturnsNull(): void
    {
        $processor = new ValidatingItemProcessor(
            fn ($item) => $item > 0,
            filter: true,
        );
        self::assertNull($processor->process(-1));
    }

    public function testInvalidItemThrowsException(): void
    {
        $processor = new ValidatingItemProcessor(fn ($item) => $item > 0);
        $this->expectException(InvalidArgumentException::class);
        $processor->process(-1);
    }

    public function testInvalidItemWithCustomException(): void
    {
        $processor = new ValidatingItemProcessor(
            fn ($item) => $item > 0,
            RuntimeException::class,
            'Must be positive',
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Must be positive');
        $processor->process(-1);
    }

    public function testValidItemPassesThrough(): void
    {
        $processor = new ValidatingItemProcessor(fn ($item) => $item > 0);
        self::assertSame(5, $processor->process(5));
    }
}
