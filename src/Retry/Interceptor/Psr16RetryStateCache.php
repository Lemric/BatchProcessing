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

namespace Lemric\BatchProcessing\Retry\Interceptor;

use JsonException;
use Lemric\BatchProcessing\Retry\RetryContextSupport;
use Psr\SimpleCache\CacheInterface;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * PSR-16 backed {@see RetryStateCacheInterface}. Serialises the {@see RetryContextSupport}
 * via JSON (round-trip safe — no closures, no resources).
 */
final readonly class Psr16RetryStateCache implements RetryStateCacheInterface
{
    public function __construct(
        private CacheInterface $cache,
        private string $namespace = 'batch.retry.',
        private ?int $ttlSeconds = 3600,
    ) {
    }

    public function delete(string $key): void
    {
        $this->cache->delete($this->namespace.$key);
    }

    public function get(string $key): ?RetryContextSupport
    {
        $raw = $this->cache->get($this->namespace.$key);
        if (!is_string($raw) || '' === $raw) {
            return null;
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return RetryContextSupport::fromArray($data);
    }

    public function put(string $key, RetryContextSupport $state): void
    {
        $payload = json_encode($state->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->cache->set($this->namespace.$key, $payload, $this->ttlSeconds);
    }
}
