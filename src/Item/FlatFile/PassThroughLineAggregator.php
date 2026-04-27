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

namespace Lemric\BatchProcessing\Item\FlatFile;

use InvalidArgumentException;
use Stringable;

/**
 * Pass-through aggregator that converts the item to string directly.
 */
final class PassThroughLineAggregator implements LineAggregatorInterface
{
    public function aggregate(mixed $item): string
    {
        return match (true) {
            null === $item => '',
            is_scalar($item), $item instanceof Stringable => (string) $item,
            default => throw new InvalidArgumentException(sprintf('PassThroughLineAggregator requires a scalar or Stringable item, got %s', get_debug_type($item))),
        };
    }
}
