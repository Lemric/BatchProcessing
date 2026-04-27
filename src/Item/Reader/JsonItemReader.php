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

use function assert;

use const JSON_THROW_ON_ERROR;

/**
 * Streaming reader for standard JSON array files ([{...}, {...}]).
 * Parses one object at a time using a simple token-based approach.
 *
 * @template TItem
 *
 * @extends AbstractItemReader<TItem>
 *
 * @implements ResourceAwareItemReaderItemStreamInterface<TItem>
 */
final class JsonItemReader extends AbstractItemReader implements ResourceAwareItemReaderItemStreamInterface
{
    private string $buffer = '';

    private string $filePath;

    /** @var resource|null */
    private $handle;

    private bool $started = false;

    /**
     * @param (callable(mixed): TItem)|null $mapper optional mapping from decoded value to TItem
     */
    public function __construct(
        string $filePath,
        private $mapper = null,
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
        if (null !== $this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
        $this->started = false;
        $this->buffer = '';
    }

    protected function doOpen(): void
    {
        try {
            SafeLocalFilePath::assertReadableLocalFile($this->filePath, $this->allowedBaseDirectory);
        } catch (NonTransientResourceException $e) {
            throw new NonTransientResourceException("JSON file not readable: {$this->filePath}", previous: $e);
        }

        $handle = fopen($this->filePath, 'r');
        if (false === $handle) {
            throw new NonTransientResourceException("Cannot open JSON file: {$this->filePath}");
        }
        $this->handle = $handle;
        $this->started = false;
        $this->buffer = '';
    }

    protected function doRead(): mixed
    {
        if (null === $this->handle) {
            $this->doOpen();
        }
        assert(null !== $this->handle);

        if (!$this->started) {
            $this->skipWhitespace();
            $ch = $this->readChar();
            if ('[' !== $ch) {
                if ($this->strict) {
                    throw new ParseException("Expected '[' at start of JSON array, got: {$ch}");
                }

                return null;
            }
            $this->started = true;
        }

        $this->skipWhitespace();
        $ch = $this->peekChar();

        if (']' === $ch || false === $ch) {
            return null;
        }

        if (',' === $ch) {
            $this->readChar();
            $this->skipWhitespace();
            $ch = $this->peekChar();
            if (']' === $ch || false === $ch) {
                return null;
            }
        }

        $json = $this->readJsonValue();
        if (null === $json) {
            return null;
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (null !== $this->mapper) {
            return ($this->mapper)($decoded);
        }

        return $decoded;
    }

    private function peekChar(): string|false
    {
        assert(null !== $this->handle);
        if ('' !== $this->buffer) {
            return $this->buffer[0];
        }

        $ch = fread($this->handle, 1);
        if (false === $ch || '' === $ch) {
            return false;
        }
        $this->buffer .= $ch;

        return $ch;
    }

    private function readChar(): string|false
    {
        assert(null !== $this->handle);
        if ('' !== $this->buffer) {
            $ch = $this->buffer[0];
            $this->buffer = mb_substr($this->buffer, 1);

            return $ch;
        }

        $ch = fread($this->handle, 1);

        return (false === $ch || '' === $ch) ? false : $ch;
    }

    private function readJsonValue(): ?string
    {
        assert(null !== $this->handle);
        $depth = 0;
        $inString = false;
        $escape = false;
        $result = '';

        while (true) {
            $ch = $this->readChar();
            if (false === $ch) {
                return '' === $result ? null : $result;
            }

            $result .= $ch;

            if ($escape) {
                $escape = false;
                continue;
            }

            if ('\\' === $ch && $inString) {
                $escape = true;
                continue;
            }

            if ('"' === $ch) {
                $inString = !$inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ('{' === $ch || '[' === $ch) {
                ++$depth;
            } elseif ('}' === $ch || ']' === $ch) {
                --$depth;
                if (0 === $depth) {
                    return $result;
                }
            } elseif (0 === $depth && (',' === $ch || ']' === $ch)) {
                // scalar value ended
                return mb_rtrim($result, ',]');
            }

            // Handle scalar top-level values (number, true, false, null).
            // At this point we are guaranteed to be outside a string (any opening/closing
            // quote toggle was handled above) and at depth 0.
            if (0 === $depth) {
                $next = $this->peekChar();
                if (',' === $next || ']' === $next || false === $next) {
                    return $result;
                }
            }
        }
    }

    private function skipWhitespace(): void
    {
        while (true) {
            $ch = $this->peekChar();
            if (false === $ch) {
                return;
            }
            if (' ' === $ch || "\t" === $ch || "\n" === $ch || "\r" === $ch) {
                $this->readChar();
            } else {
                return;
            }
        }
    }
}
