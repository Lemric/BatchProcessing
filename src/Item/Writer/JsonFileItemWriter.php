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
use Lemric\BatchProcessing\Item\ResourceAwareItemWriterItemStreamInterface;
use Lemric\BatchProcessing\Security\SafeLocalFilePath;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;
use const SEEK_END;

/**
 * Writes items as a JSON array to a file ([{...}, {...}]).
 * Uses streaming output so items are not buffered entirely in memory.
 *
 * @template TItem
 *
 * @extends AbstractItemWriter<TItem>
 *
 * @implements ResourceAwareItemWriterItemStreamInterface<TItem>
 */
final class JsonFileItemWriter extends AbstractItemWriter implements ResourceAwareItemWriterItemStreamInterface
{
    private const string STATE_ITEMS_WRITTEN = 'json.items.written';

    private string $filePath;

    /** @var resource|null */
    private $handle;

    private int $itemsWritten = 0;

    /**
     * @param callable(): string|null $headerCallback
     * @param callable(): string|null $footerCallback
     */
    public function __construct(
        string $filePath,
        private $headerCallback = null,
        private $footerCallback = null,
        private readonly int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        private readonly ?string $allowedBaseDirectory = null,
    ) {
        $this->filePath = $filePath;
    }

    public function close(): void
    {
        if (null !== $this->handle) {
            $handle = $this->handle;
            fwrite($handle, "\n]");

            if (null !== $this->footerCallback) {
                $footer = ($this->footerCallback)();
                fwrite($handle, "\n".$footer);
            }

            // Truncate at current position to remove any leftover data from previous longer writes.
            $pos = ftell($handle);
            if (false !== $pos && $pos >= 0) {
                ftruncate($handle, $pos);
            }
            fclose($handle);
            $this->handle = null;
        }
        $this->itemsWritten = 0;
    }

    public function open(ExecutionContext $executionContext): void
    {
        if ($executionContext->containsKey(self::STATE_ITEMS_WRITTEN)) {
            $this->itemsWritten = $executionContext->getInt(self::STATE_ITEMS_WRITTEN);
        }

        $isRestart = $this->itemsWritten > 0;

        try {
            SafeLocalFilePath::assertWritableLocalPath($this->filePath, $this->allowedBaseDirectory);
        } catch (NonTransientResourceException $e) {
            throw new ItemWriterException('Cannot write to target path: '.$this->filePath, previous: $e);
        }

        if ($isRestart) {
            // On restart, open in r+ mode to overwrite the closing bracket
            $handle = fopen($this->filePath, 'r+');
            if (false === $handle) {
                throw new ItemWriterException("Cannot reopen JSON file: {$this->filePath}");
            }
            // Seek to just before the closing bracket ']'
            fseek($handle, -1, SEEK_END);
            $this->handle = $handle;
        } else {
            $handle = fopen($this->filePath, 'w');
            if (false === $handle) {
                throw new ItemWriterException("Cannot open JSON file for writing: {$this->filePath}");
            }
            $this->handle = $handle;

            if (null !== $this->headerCallback) {
                $header = ($this->headerCallback)();
                fwrite($this->handle, $header);
            }

            fwrite($this->handle, '[');
        }
    }

    public function setResource(string $resource): void
    {
        $this->filePath = $resource;
    }

    public function update(ExecutionContext $executionContext): void
    {
        $executionContext->put(self::STATE_ITEMS_WRITTEN, $this->itemsWritten);
        if (null !== $this->handle) {
            // Write closing bracket at current position (will be overwritten on next write)
            $pos = ftell($this->handle);
            fwrite($this->handle, "\n]");
            fflush($this->handle);
            // Seek back so next write overwrites the bracket
            if (false !== $pos) {
                fseek($this->handle, $pos);
            }
        }
    }

    public function write(Chunk $items): void
    {
        if (null === $this->handle) {
            throw new ItemWriterException('Writer is not open. Call open() first.');
        }

        foreach ($items->getOutputItems() as $item) {
            if ($this->itemsWritten > 0) {
                fwrite($this->handle, ',');
            }
            fwrite($this->handle, "\n");
            fwrite($this->handle, json_encode($item, $this->jsonFlags | JSON_THROW_ON_ERROR));
            ++$this->itemsWritten;
        }
    }
}
