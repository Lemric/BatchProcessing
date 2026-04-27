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

/**
 * Pass-through extractor that expects the item to already be an array.
 */
final class PassThroughFieldExtractor implements FieldExtractorInterface
{
    public function extract(mixed $item): array
    {
        if (is_array($item)) {
            return array_values($item);
        }

        return [$item];
    }
}
