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

/**
 * Logical Redis backing data structure consumed by {@see RedisItemReader}/{@see RedisItemWriter}.
 */
enum RedisDataStructure: string
{
    case LIST = 'list';

    case SET = 'set';

    case STREAM = 'stream';
}
