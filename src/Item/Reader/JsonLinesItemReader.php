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

use Lemric\BatchProcessing\Exception\{NonTransientResourceException, ParseException, UnexpectedInputException};
use Lemric\BatchProcessing\Security\SafeLocalFilePath;

use function assert;

use const JSON_THROW_ON_ERROR;

/**
 * Streaming JSON Lines (JSONL) reader. Reads one JSON object per line, keeping memory usage
 * constant regardless of file size. Each line must be a valid JSON value.
 *
 * @template TItem
 *
 * @extends AbstractItemReader<TItem>
 */
final class JsonLinesItemReader extends AbstractItemReader
{
    /** @var resource|null */
    private $handle;

    /**
     * @param (callable(mixed): TItem)|null $mapper optional mapping from decoded value to TItem
     */
    public function __construct(
        private readonly string $filePath,
        private $mapper = null,
        private readonly bool $strict = true,
        ?string $name = null,
        bool $saveState = true,
        private readonly ?string $allowedBaseDirectory = null,
    ) {
        parent::__construct($name, $saveState);
    }

    protected function doClose(): void
    {
        if (null !== $this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    protected function doOpen(): void
    {
        try {
            SafeLocalFilePath::assertReadableLocalFile($this->filePath, $this->allowedBaseDirectory);
        } catch (NonTransientResourceException $e) {
            throw new NonTransientResourceException("JSONL file not readable: {$this->filePath}", previous: $e);
        }

        $handle = fopen($this->filePath, 'r');
        if (false === $handle) {
            throw new NonTransientResourceException("Cannot open JSONL file: {$this->filePath}");
        }
        $this->handle = $handle;
    }

    protected function doRead(): mixed
    {
        if (null === $this->handle) {
            $this->doOpen();
        }
        assert(null !== $this->handle);

        do {
            $line = fgets($this->handle);
            if (false === $line) {
                return null;
            }
            $line = mb_trim($line);
        } while ('' === $line);

        $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        if (null === $decoded && 'null' !== $line) {
            if ($this->strict) {
                throw new ParseException("Invalid JSON on line: {$line}");
            }

            return null;
        }

        // When no mapper is configured, callers expect a structured row (object/array). Scalar
        // payloads are typically a sign of malformed input rather than a parse failure.
        if (null === $this->mapper && null !== $decoded && !is_array($decoded)) {
            if ($this->strict) {
                throw new UnexpectedInputException(sprintf('Expected JSON object/array on line, got %s: %s', get_debug_type($decoded), $line));
            }

            return null;
        }

        if (null !== $this->mapper) {
            return ($this->mapper)($decoded);
        }

        return $decoded;
    }
}
