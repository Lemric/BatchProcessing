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
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Exception\NonTransientResourceException;
use Lemric\BatchProcessing\Item\{ItemStreamInterface, ItemWriterInterface};
use Lemric\BatchProcessing\Security\SafeLocalFilePath;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * JSON item writer backed by Symfony Serializer. Buffers serialized chunks and writes a
 * single JSON array to a destination file/handle on {@see close()} (suitable for one-shot
 * exports). For streaming line-delimited JSON, set {@code $jsonLines = true}.
 *
 * Requires {@code symfony/serializer} (suggested dependency).
 *
 * @template TItem
 *
 * @implements ItemWriterInterface<TItem>
 */
final class SerializerJsonItemWriter implements ItemWriterInterface, ItemStreamInterface
{
    /** @var list<string> */
    private array $buffer = [];

    /** @var resource|null */
    private $handle;

    /**
     * @param resource|string|SplFileInfo $destination
     * @param array<string, mixed>        $context     passed verbatim to {@see SerializerInterface::serialize()}
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly mixed $destination,
        private readonly array $context = [],
        private readonly bool $jsonLines = false,
        private readonly ?string $allowedBaseDirectory = null,
    ) {
    }

    public function close(): void
    {
        if (null === $this->handle) {
            return;
        }
        if ($this->jsonLines) {
            // already flushed during write()
        } else {
            fwrite($this->handle, '['.implode(',', $this->buffer).']');
        }
        if (!is_resource($this->destination)) {
            fclose($this->handle);
        }
        $this->handle = null;
        $this->buffer = [];
    }

    public function open(ExecutionContext $executionContext): void
    {
        $this->buffer = [];
        $this->handle = $this->openDestination();
    }

    public function update(ExecutionContext $executionContext): void
    {
        // Buffered; nothing to checkpoint until close().
    }

    public function write(Chunk $items): void
    {
        if (null === $this->handle) {
            throw new RuntimeException('SerializerJsonItemWriter::open() was not called.');
        }
        foreach ($items->getOutputItems() as $item) {
            /** @var string $serialized */
            $serialized = $this->serializer->serialize($item, 'json', $this->context);
            if ($this->jsonLines) {
                fwrite($this->handle, $serialized."\n");
                continue;
            }
            $this->buffer[] = $serialized;
        }
    }

    /**
     * @return resource
     */
    private function openDestination()
    {
        if (is_resource($this->destination)) {
            return $this->destination;
        }
        $path = $this->destination instanceof SplFileInfo
            ? (string) $this->destination->getPathname()
            : (is_string($this->destination) ? $this->destination : throw new InvalidArgumentException('Unsupported destination type.'));
        try {
            SafeLocalFilePath::assertWritableLocalPath($path, $this->allowedBaseDirectory);
        } catch (NonTransientResourceException $e) {
            throw new RuntimeException('Could not open JSON destination: '.$path, 0, $e);
        }
        $handle = fopen($path, 'wb');
        if (false === $handle) {
            throw new RuntimeException('Could not open JSON destination: '.$path);
        }

        return $handle;
    }
}
