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

use Closure;
use Lemric\BatchProcessing\Item\ItemReaderInterface;
use Lemric\BatchProcessing\Security\RedisKeyValidator;
use RuntimeException;

/**
 * Reads items from a Redis LIST / STREAM / SET. Compatible with both ext-redis (\Redis) and
 * Predis (\Predis\ClientInterface) — accessed through the narrow {@see callable} {@code $command}
 * dispatcher to avoid hard dependencies.
 *
 * Modes:
 *  - LIST   → LPOP key
 *  - SET    → SPOP key
 *  - STREAM → XREAD COUNT 1 STREAMS key 0  (auto-advances last seen id)
 *
 * For STREAM mode the reader maintains the last consumed id in-memory; for full restart
 * support combine with a custom {@see \Lemric\BatchProcessing\Item\ItemStreamInterface} that
 * persists the cursor into the {@see \Lemric\BatchProcessing\Domain\ExecutionContext}.
 *
 * @implements ItemReaderInterface<mixed>
 */
final class RedisItemReader implements ItemReaderInterface
{
    private string $lastStreamId = '0';

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

    public function read(): mixed
    {
        return match ($this->structure) {
            RedisDataStructure::LIST => $this->normalize($this->call('lpop', $this->key)),
            RedisDataStructure::SET => $this->normalize($this->call('spop', $this->key)),
            RedisDataStructure::STREAM => $this->readStream(),
        };
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
        // Predis dynamic dispatch
        if (method_exists($client, '__call')) {
            return $client->{$command}(...$args);
        }
        throw new RuntimeException("Redis client does not support command: {$command}");
    }

    private function normalize(mixed $value): mixed
    {
        if (false === $value || null === $value) {
            return null;
        }

        return $value;
    }

    private function readStream(): mixed
    {
        /** @var array<mixed>|null $result */
        $result = $this->call('xread', ['COUNT', 1, 'STREAMS', $this->key, $this->lastStreamId]);
        if (null === $result || [] === $result) {
            return null;
        }
        // Normalise both ext-redis and Predis layouts: { key => [ [id, [field=>value, ...]], ... ] }
        $entries = reset($result);
        if (!is_array($entries) || [] === $entries) {
            return null;
        }
        $first = reset($entries);
        if (!is_array($first) || count($first) < 2) {
            return null;
        }
        /** @var string $id */
        $id = $first[0];
        $payload = $first[1];
        $this->lastStreamId = $id;

        return $payload;
    }
}
