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

namespace Lemric\BatchProcessing\Exception;

/**
 * Marker exception used by user-supplied processors / readers / writers to signal an item
 * that may safely be skipped according to the configured {@see SkipPolicyInterface}.
 *
 * All bundled policies that consult exception types honor this marker in production code:
 * {@see LimitCheckingItemSkipPolicy}, {@see CountingSkipPolicy}, {@see ExceptionHierarchySkipPolicy},
 * {@see ExceptionClassifierSkipPolicy}. Compose {@see CompositeSkipPolicy} with one of the above
 * if you combine multiple strategies.
 */
class SkippableException extends BatchException
{
}
