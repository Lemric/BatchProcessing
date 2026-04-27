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
 * Thrown when an optimistic locking conflict is detected during a repository update
 * (the row version in the database does not match the expected version).
 */
class OptimisticLockingFailureException extends RepositoryException
{
}
