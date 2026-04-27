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

namespace Lemric\BatchProcessing\Item\Writer;

use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Exception\{ItemWriterException, NonTransientResourceException};
use Lemric\BatchProcessing\Item\FlatFile\LineAggregatorInterface;
use Lemric\BatchProcessing\Item\ResourceAwareItemWriterItemStreamInterface;
use Lemric\BatchProcessing\Security\SafeLocalFilePath;

use const PHP_EOL;

/**
 * Writes items to a flat file using a {@see LineAggregatorInterface} for line conversion.
 * Supports header/footer callbacks, append mode, encoding and custom line separators.
 *
 * @template TItem
 *
 * @extends AbstractItemWriter<TItem>
 *
 * @implements ResourceAwareItemWriterItemStreamInterface<TItem>
 */
final class FlatFileItemWriter extends AbstractItemWriter implements ResourceAwareItemWriterItemStreamInterface
{
    private const string STATE_LINES_WRITTEN = 'flatfile.lines.written';

    private string $filePath;

    /** @var resource|null */
    private $handle;

    private bool $headerWritten = false;

    private int $linesWritten = 0;

    /**
     * @param callable(): string|null $headerCallback returns header line(s) or null
     * @param callable(): string|null $footerCallback returns footer line(s) or null
     */
    public function __construct(
        string $filePath,
        private readonly LineAggregatorInterface $lineAggregator,
        private $headerCallback = null,
        private $footerCallback = null,
        private readonly bool $append = false,
        private readonly string $encoding = 'UTF-8',
        private readonly string $lineSeparator = PHP_EOL,
        private readonly ?string $allowedBaseDirectory = null,
    ) {
        $this->filePath = $filePath;
    }

    public function close(): void
    {
        if (null !== $this->handle) {
            $handle = $this->handle;
            if (null !== $this->footerCallback) {
                $footer = ($this->footerCallback)();
                $this->writeLine($footer);
            }
            fclose($handle);
            $this->handle = null;
        }
        $this->linesWritten = 0;
        $this->headerWritten = false;
    }

    public function open(ExecutionContext $executionContext): void
    {
        if ($executionContext->containsKey(self::STATE_LINES_WRITTEN)) {
            $this->linesWritten = $executionContext->getInt(self::STATE_LINES_WRITTEN);
        }

        try {
            SafeLocalFilePath::assertWritableLocalPath($this->filePath, $this->allowedBaseDirectory);
        } catch (NonTransientResourceException $e) {
            throw new ItemWriterException('Cannot write to target path: '.$this->filePath, previous: $e);
        }

        $mode = $this->append || $this->linesWritten > 0 ? 'a' : 'w';
        $handle = fopen($this->filePath, $mode);
        if (false === $handle) {
            throw new ItemWriterException("Cannot open file for writing: {$this->filePath}");
        }
        $this->handle = $handle;
        $this->headerWritten = $this->append || $this->linesWritten > 0;
    }

    public function setResource(string $resource): void
    {
        $this->filePath = $resource;
    }

    public function update(ExecutionContext $executionContext): void
    {
        $executionContext->put(self::STATE_LINES_WRITTEN, $this->linesWritten);
        if (null !== $this->handle) {
            fflush($this->handle);
        }
    }

    public function write(Chunk $items): void
    {
        if (null === $this->handle) {
            throw new ItemWriterException('Writer is not open. Call open() first.');
        }

        if (!$this->headerWritten && null !== $this->headerCallback) {
            $header = ($this->headerCallback)();
            $this->writeLine($header);
            $this->headerWritten = true;
        }

        foreach ($items->getOutputItems() as $item) {
            $line = $this->lineAggregator->aggregate($item);
            $this->writeLine($line);
            ++$this->linesWritten;
        }
    }

    private function writeLine(string $line): void
    {
        if (null === $this->handle) {
            return;
        }

        $encoded = 'UTF-8' !== $this->encoding
            ? mb_convert_encoding($line, $this->encoding, 'UTF-8')
            : $line;

        fwrite($this->handle, $encoded.$this->lineSeparator);
    }
}
