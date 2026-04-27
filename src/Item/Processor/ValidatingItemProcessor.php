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

namespace Lemric\BatchProcessing\Item\Processor;

use InvalidArgumentException;
use Lemric\BatchProcessing\Item\ItemProcessorInterface;
use Throwable;

/**
 * Validates items using a user-supplied predicate, throwing an exception for invalid items.
 * Unlike {@see FilteringItemProcessor} which silently drops items, this processor signals
 * validation failures explicitly so the retry/skip framework can handle them.
 *
 * @template TItem
 *
 * @implements ItemProcessorInterface<TItem, TItem>
 */
final class ValidatingItemProcessor implements ItemProcessorInterface
{
    /**
     * @param callable(TItem): bool        $validator      returns true if the item is valid
     * @param class-string<Throwable>|null $exceptionClass exception to throw on invalid items
     */
    public function __construct(
        private $validator,
        private readonly ?string $exceptionClass = null,
        private readonly string $message = 'Validation failed for item.',
        private readonly bool $filter = false,
    ) {
    }

    public function process(mixed $item): mixed
    {
        if (($this->validator)($item)) {
            return $item;
        }

        if ($this->filter) {
            return null;
        }

        $exClass = $this->exceptionClass ?? InvalidArgumentException::class;

        throw new $exClass($this->message);
    }
}
