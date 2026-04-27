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
use Lemric\BatchProcessing\Exception\ParseException;
use Lemric\BatchProcessing\Item\FlatFile\{
    BeanWrapperFieldExtractor,
    DefaultFieldSet,
    DefaultLineMapper,
    DelimitedLineAggregator,
    DelimitedLineTokenizer,
    FieldSetFactory,
    FieldSetMapperInterface,
    FixedLengthTokenizer,
    FormatterLineAggregator,
    PassThroughFieldExtractor,
    PassThroughLineAggregator,
    PatternMatchingCompositeLineTokenizer,
};
use PHPUnit\Framework\TestCase;

final class FlatFileTest extends TestCase
{
    public function testBeanWrapperFieldExtractorWithArray(): void
    {
        $extractor = new BeanWrapperFieldExtractor(['name', 'age']);
        self::assertSame(['Alice', 30], $extractor->extract(['name' => 'Alice', 'age' => 30]));
    }

    public function testBeanWrapperFieldExtractorWithObject(): void
    {
        $obj = new class {
            public function getName(): string
            {
                return 'Bob';
            }

            public function getAge(): int
            {
                return 25;
            }
        };

        $extractor = new BeanWrapperFieldExtractor(['name', 'age']);
        self::assertSame(['Bob', 25], $extractor->extract($obj));
    }

    public function testDefaultFieldSetReadBoolean(): void
    {
        $fs = new DefaultFieldSet(['true', '0', 'yes', 'no']);
        self::assertTrue($fs->readBoolean(0));
        self::assertFalse($fs->readBoolean(1));
        self::assertTrue($fs->readBoolean(2));
        self::assertFalse($fs->readBoolean(3));
    }

    public function testDefaultFieldSetReadByIndex(): void
    {
        $fs = new DefaultFieldSet(['Alice', '30', '1.75']);
        self::assertSame('Alice', $fs->readString(0));
        self::assertSame(30, $fs->readInt(1));
        self::assertSame(1.75, $fs->readFloat(2));
    }

    public function testDefaultFieldSetReadByName(): void
    {
        $fs = new DefaultFieldSet(['Alice', '30'], ['name', 'age']);
        self::assertSame('Alice', $fs->readString('name'));
        self::assertSame(30, $fs->readInt('age'));
    }

    public function testDefaultFieldSetReadDate(): void
    {
        $fs = new DefaultFieldSet(['2025-01-15']);
        $date = $fs->readDate(0);
        self::assertSame('2025-01-15', $date->format('Y-m-d'));
    }

    public function testDefaultLineMapper(): void
    {
        $mapper = new DefaultLineMapper(
            new DelimitedLineTokenizer(',', '"', ['name', 'age']),
            new class implements FieldSetMapperInterface {
                public function mapFieldSet(\Lemric\BatchProcessing\Item\FlatFile\FieldSet $fieldSet): mixed
                {
                    return ['name' => $fieldSet->readString('name'), 'age' => $fieldSet->readInt('age')];
                }
            },
        );

        $result = $mapper->mapLine('Alice,30', 1);
        self::assertSame(['name' => 'Alice', 'age' => 30], $result);
    }

    public function testDelimitedLineAggregator(): void
    {
        $agg = new DelimitedLineAggregator(new PassThroughFieldExtractor(), ',');
        self::assertSame('a,b,c', $agg->aggregate(['a', 'b', 'c']));
    }

    public function testDelimitedLineTokenizer(): void
    {
        $tokenizer = new DelimitedLineTokenizer(',', '"', ['name', 'age']);
        $fs = $tokenizer->tokenize('Alice,30');
        self::assertSame('Alice', $fs->readString('name'));
        self::assertSame(30, $fs->readInt('age'));
    }

    public function testFieldSetFactoryCreatesWithNames(): void
    {
        $factory = new FieldSetFactory(['name', 'age']);
        $fs = $factory->create(['Alice', '30']);
        self::assertSame('Alice', $fs->readString('name'));
    }

    public function testFieldSetOutOfRange(): void
    {
        $fs = new DefaultFieldSet(['a']);
        $this->expectException(InvalidArgumentException::class);
        $fs->readString(5);
    }

    public function testFixedLengthTokenizer(): void
    {
        $tokenizer = new FixedLengthTokenizer([[0, 10], [10, 15]], ['name', 'age']);
        $fs = $tokenizer->tokenize('Alice     30   ');
        self::assertSame('Alice', $fs->readString('name'));
        self::assertSame(30, $fs->readInt('age'));
    }

    public function testFormatterLineAggregator(): void
    {
        $agg = new FormatterLineAggregator(new PassThroughFieldExtractor(), '%-10s|%5d');
        self::assertSame('Alice     |   30', $agg->aggregate(['Alice', 30]));
    }

    public function testPassThroughFieldExtractor(): void
    {
        $extractor = new PassThroughFieldExtractor();
        self::assertSame(['a', 'b'], $extractor->extract(['a', 'b']));
        self::assertSame([42], $extractor->extract(42));
    }

    public function testPassThroughLineAggregator(): void
    {
        $agg = new PassThroughLineAggregator();
        self::assertSame('hello', $agg->aggregate('hello'));
    }

    public function testPatternMatchingCompositeLineTokenizer(): void
    {
        $csv = new DelimitedLineTokenizer(',');
        $fixed = new FixedLengthTokenizer([[0, 5], [5, 10]]);

        $tokenizer = new PatternMatchingCompositeLineTokenizer([
            '/,/' => $csv,
            '/^\w{5}\d{5}$/' => $fixed,
        ]);

        $fs = $tokenizer->tokenize('a,b');
        self::assertSame('a', $fs->readString(0));
    }

    public function testPatternMatchingThrowsWhenNoMatch(): void
    {
        $tokenizer = new PatternMatchingCompositeLineTokenizer(['/^NEVER$/' => new DelimitedLineTokenizer()]);
        $this->expectException(ParseException::class);
        $tokenizer->tokenize('something else');
    }
}
