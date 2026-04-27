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
 * Maps a {@see FieldSet} to a domain object.
 *
 * @template TItem
 */
interface FieldSetMapperInterface
{
    /**
     * @return TItem
     */
    public function mapFieldSet(FieldSet $fieldSet): mixed;
}
