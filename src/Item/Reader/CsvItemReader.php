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

namespace Lemric\BatchProcessing\Item\Reader;

use Lemric\BatchProcessing\Exception\{NonTransientResourceException, ParseException};
use Lemric\BatchProcessing\Item\ResourceAwareItemReaderItemStreamInterface;
use Lemric\BatchProcessing\Security\SafeLocalFilePath;
use RuntimeException;
use SplFileObject;
use Throwable;

use function assert;

/**
 * Streaming CSV reader based on {@see SplFileObject}. Reads one line at a time and produces
 * items via a user-supplied field-set mapper.
 *
 * @template TItem
 *
 * @extends AbstractItemReader<TItem>
 *
 * @implements ResourceAwareItemReaderItemStreamInterface<TItem>
 */
final class CsvItemReader extends AbstractItemReader implements ResourceAwareItemReaderItemStreamInterface
{
    private ?SplFileObject $file = null;

    private string $filePath;

    /**
     * @param callable(list<string>, int): TItem|CsvFieldSetMapperInterface<TItem> $fieldSetMapper
     *                                                                                             Either a callable mapping (row, line) → TItem, or a {@see CsvFieldSetMapperInterface}
     *                                                                                             whose {@see CsvFieldSetMapperInterface::mapFieldSet()} receives the row
     */
    public function __construct(
        string $filePath,
        private $fieldSetMapper,
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
        private readonly int $linesToSkip = 0,
        private readonly bool $strict = true,
        ?string $name = null,
        bool $saveState = true,
        private readonly ?string $allowedBaseDirectory = null,
    ) {
        $this->filePath = $filePath;
        parent::__construct($name, $saveState);
    }

    public function setResource(string $resource): void
    {
        $this->filePath = $resource;
    }

    protected function doClose(): void
    {
        $this->file = null;
    }

    protected function doOpen(): void
    {
        try {
            SafeLocalFilePath::assertReadableLocalFile($this->filePath, $this->allowedBaseDirectory);
        } catch (NonTransientResourceException $e) {
            throw new NonTransientResourceException("CSV file not readable: {$this->filePath}", previous: $e);
        }

        try {
            $this->file = new SplFileObject($this->filePath, 'r');
        } catch (RuntimeException $e) {
            throw new NonTransientResourceException("Cannot open CSV file: {$this->filePath}", previous: $e);
        }
        $this->file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        for ($i = 0; $i < $this->linesToSkip; ++$i) {
            if ($this->file->eof()) {
                break;
            }
            $this->file->fgetcsv();
        }
    }

    protected function doRead(): mixed
    {
        if (null === $this->file) {
            $this->doOpen();
        }
        assert(null !== $this->file);

        if ($this->file->eof()) {
            return null;
        }

        /** @var list<string|null>|false $row */
        $row = $this->file->fgetcsv();
        if (false === $row || $row === [null]) {
            return null;
        }

        $line = $this->file->key() + 1;
        /** @var list<string> $normalized */
        $normalized = array_map(static fn ($v): string => (string) ($v ?? ''), $row);

        try {
            return $this->fieldSetMapper instanceof CsvFieldSetMapperInterface
                ? $this->fieldSetMapper->mapFieldSet($normalized)
                : ($this->fieldSetMapper)($normalized, $line);
        } catch (Throwable $e) {
            if ($this->strict) {
                throw new ParseException("Failed to parse line {$line} of {$this->filePath}: ".$e->getMessage(), previous: $e);
            }

            return null;
        }
    }
}
