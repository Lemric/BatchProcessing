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

namespace Lemric\BatchProcessing\Retry;

use RuntimeException;
use Throwable;

/**
 * Serializable companion data for {@see RetryContext}. Carries the
 * minimal state required to resume a stateful retry from a persistence layer (PSR-16 cache,
 * job ExecutionContext, etc.).
 *
 * Throwables are stored as a triplet (class, message, code, trace-as-string) because PHP's
 * native {@see Throwable} hierarchy is not portably serializable across versions/SAPIs.
 */
final class RetryContextSupport
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly int $retryCount,
        public readonly bool $exhausted,
        public readonly ?string $lastThrowableClass,
        public readonly ?string $lastThrowableMessage,
        public readonly int $lastThrowableCode,
        public readonly ?string $lastThrowableTrace,
        public readonly array $attributes = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var int $retryCount */
        $retryCount = $data['retryCount'] ?? 0;
        /** @var bool $exhausted */
        $exhausted = $data['exhausted'] ?? false;
        /** @var string|null $lastThrowableClass */
        $lastThrowableClass = $data['lastThrowableClass'] ?? null;
        /** @var string|null $lastThrowableMessage */
        $lastThrowableMessage = $data['lastThrowableMessage'] ?? null;
        /** @var int $lastThrowableCode */
        $lastThrowableCode = $data['lastThrowableCode'] ?? 0;
        /** @var string|null $lastThrowableTrace */
        $lastThrowableTrace = $data['lastThrowableTrace'] ?? null;
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        return new self(
            retryCount: (int) $retryCount,
            exhausted: (bool) $exhausted,
            lastThrowableClass: is_string($lastThrowableClass) ? $lastThrowableClass : null,
            lastThrowableMessage: is_string($lastThrowableMessage) ? $lastThrowableMessage : null,
            lastThrowableCode: (int) $lastThrowableCode,
            lastThrowableTrace: is_string($lastThrowableTrace) ? $lastThrowableTrace : null,
            attributes: $attributes,
        );
    }

    public static function fromContext(RetryContext $context): self
    {
        $t = $context->getLastThrowable();

        return new self(
            retryCount: $context->getRetryCount(),
            exhausted: $context->isExhausted(),
            lastThrowableClass: null === $t ? null : get_class($t),
            lastThrowableMessage: $t?->getMessage(),
            lastThrowableCode: (int) ($t?->getCode() ?? 0),
            lastThrowableTrace: $t?->getTraceAsString(),
            attributes: [], // Attributes are intentionally omitted (may contain non-serializable closures).
        );
    }

    /**
     * Rebuilds a {@see RetryContext} initialised with the persisted retry counter and a
     * synthetic {@see RestoredThrowable} as last throwable (concrete class identity is lost
     * by design — consumers should rely on {@see $lastThrowableClass} for branching).
     */
    public function restore(): RetryContext
    {
        $context = new RetryContext();
        for ($i = 0; $i < $this->retryCount; ++$i) {
            $context->registerThrowable($this->buildSyntheticThrowable());
        }
        if ($this->exhausted) {
            $context->setExhausted();
        }
        foreach ($this->attributes as $key => $value) {
            $context->setAttribute((string) $key, $value);
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'retryCount' => $this->retryCount,
            'exhausted' => $this->exhausted,
            'lastThrowableClass' => $this->lastThrowableClass,
            'lastThrowableMessage' => $this->lastThrowableMessage,
            'lastThrowableCode' => $this->lastThrowableCode,
            'lastThrowableTrace' => $this->lastThrowableTrace,
            'attributes' => $this->attributes,
        ];
    }

    private function buildSyntheticThrowable(): Throwable
    {
        return new RuntimeException(
            sprintf(
                '[restored:%s] %s',
                $this->lastThrowableClass ?? 'Throwable',
                $this->lastThrowableMessage ?? '',
            ),
            $this->lastThrowableCode,
        );
    }
}
