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

use Lemric\BatchProcessing\Item\ItemProcessorInterface;

/**
 * Validates items using a PSR-compatible validator. Items that fail validation are
 * filtered (returns null) or throw an exception depending on the configured mode.
 *
 * Designed to be subclassed/decorated via Symfony Bridge or Laravel Bridge with their
 * native validators.
 *
 * @template T
 *
 * @implements ItemProcessorInterface<T, T>
 */
final class BeanValidatingItemProcessor implements ItemProcessorInterface
{
    /**
     * @param callable(T): list<string> $validator Returns list of error messages (empty = valid)
     * @param bool                      $filter    true = filter invalid items; false = throw on invalid
     */
    public function __construct(
        private readonly mixed $validator,
        private readonly bool $filter = false,
    ) {
    }

    public function process(mixed $item): mixed
    {
        $errors = ($this->validator)($item);
        if ([] !== $errors) {
            if ($this->filter) {
                return null;
            }
            throw new \Lemric\BatchProcessing\Exception\BatchException('Validation failed: '.implode('; ', $errors));
        }

        return $item;
    }
}
