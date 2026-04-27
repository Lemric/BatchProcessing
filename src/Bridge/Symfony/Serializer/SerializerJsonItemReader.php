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

namespace Lemric\BatchProcessing\Bridge\Symfony\Serializer;

use InvalidArgumentException;
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Exception\NonTransientResourceException;
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemStreamInterface};
use Lemric\BatchProcessing\Security\SafeLocalFilePath;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * JSON item reader backed by Symfony Serializer. Reads a JSON array from {@code $resource}
 * (string or open file handle) once on {@see open()}, then yields one decoded item per
 * {@see read()} call.
 *
 * Requires {@code symfony/serializer} (suggested dependency).
 *
 * @template TItem
 *
 * @implements ItemReaderInterface<TItem>
 */
final class SerializerJsonItemReader implements ItemReaderInterface, ItemStreamInterface
{
    private const string CTX_INDEX = 'serializer.json.reader.index';

    private int $cursor = 0;

    /** @var list<TItem> */
    private array $items = [];

    /**
     * @param class-string<TItem>         $itemType
     * @param resource|string|SplFileInfo $source
     * @param array<string, mixed>        $context  passed verbatim to {@see SerializerInterface::deserialize()}
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly mixed $source,
        private readonly string $itemType,
        private readonly array $context = [],
        private readonly ?string $allowedBaseDirectory = null,
    ) {
    }

    public function close(): void
    {
        $this->items = [];
        $this->cursor = 0;
    }

    public function open(ExecutionContext $executionContext): void
    {
        $payload = $this->loadPayload();
        /** @var list<TItem> $items */
        $items = $this->serializer->deserialize($payload, $this->itemType.'[]', 'json', $this->context);
        $this->items = $items;
        $this->cursor = $executionContext->getInt(self::CTX_INDEX, 0);
    }

    public function read(): mixed
    {
        if ($this->cursor >= count($this->items)) {
            return null;
        }

        return $this->items[$this->cursor++];
    }

    public function update(ExecutionContext $executionContext): void
    {
        $executionContext->put(self::CTX_INDEX, $this->cursor);
    }

    private function loadPayload(): string
    {
        if (is_resource($this->source)) {
            $contents = stream_get_contents($this->source);
            if (false === $contents) {
                throw new RuntimeException('Could not read JSON source stream.');
            }

            return $contents;
        }
        if ($this->source instanceof SplFileInfo) {
            $path = $this->source->getPathname();
            try {
                SafeLocalFilePath::assertReadableLocalFile($path, $this->allowedBaseDirectory);
            } catch (NonTransientResourceException $e) {
                throw new RuntimeException('Could not read JSON file: '.$path, 0, $e);
            }
            $contents = file_get_contents($path);
            if (false === $contents) {
                throw new RuntimeException('Could not read JSON file: '.$path);
            }

            return $contents;
        }
        if (is_string($this->source)) {
            return $this->source;
        }
        throw new InvalidArgumentException('Unsupported JSON source type: '.get_debug_type($this->source));
    }
}
