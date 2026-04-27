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

use Closure;
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;
use Lemric\BatchProcessing\Item\Reader\RedisDataStructure;
use Lemric\BatchProcessing\Security\RedisKeyValidator;
use RuntimeException;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Writes a chunk of items to a Redis LIST / STREAM / SET.
 *
 * Modes:
 *  - LIST   → RPUSH key item
 *  - SET    → SADD key item
 *  - STREAM → XADD key * payload (payload must be array<string,scalar>)
 *
 * @implements ItemWriterInterface<mixed>
 */
final class RedisItemWriter implements ItemWriterInterface
{
    /**
     * @param object|Closure $client \Redis|\Predis\ClientInterface or callable($cmd, ...$args): mixed
     */
    public function __construct(
        private readonly object $client,
        private readonly string $key,
        private readonly RedisDataStructure $structure = RedisDataStructure::LIST,
    ) {
        RedisKeyValidator::assertSafeKey($this->key);
    }

    public function write(Chunk $items): void
    {
        foreach ($items->getOutputItems() as $item) {
            match ($this->structure) {
                RedisDataStructure::LIST => $this->call('rpush', $this->key, $this->scalarize($item)),
                RedisDataStructure::SET => $this->call('sadd', $this->key, $this->scalarize($item)),
                RedisDataStructure::STREAM => $this->call('xadd', $this->key, '*', $this->streamFields($item)),
            };
        }
    }

    private function call(string $command, mixed ...$args): mixed
    {
        $client = $this->client;
        if ($client instanceof Closure) {
            return ($client)($command, ...$args);
        }
        if (method_exists($client, $command)) {
            /** @var callable $callable */
            $callable = [$client, $command];

            return $callable(...$args);
        }
        if (method_exists($client, '__call')) {
            return $client->{$command}(...$args);
        }
        throw new RuntimeException("Redis client does not support command: {$command}");
    }

    private function scalarize(mixed $item): string
    {
        if (is_string($item)) {
            return $item;
        }
        if (is_scalar($item)) {
            return (string) $item;
        }

        return json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, string>
     */
    private function streamFields(mixed $item): array
    {
        if (!is_array($item)) {
            return ['payload' => $this->scalarize($item)];
        }
        $out = [];
        foreach ($item as $k => $v) {
            $out[(string) $k] = $this->scalarize($v);
        }

        return $out;
    }
}
