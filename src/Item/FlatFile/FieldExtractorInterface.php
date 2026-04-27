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
 * Extracts fields from a domain object for line aggregation.
 */
interface FieldExtractorInterface
{
    /**
     * @return list<mixed>
     */
    public function extract(mixed $item): array;
}
