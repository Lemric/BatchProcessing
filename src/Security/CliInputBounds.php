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

namespace Lemric\BatchProcessing\Security;

use InvalidArgumentException;

/**
 * Centralized bounds for CLI and operational interfaces (DoS protection).
 */
final class CliInputBounds
{
    public const int MAX_LIST_LIMIT = 500;

    public const int MIN_EXECUTION_ID = 1;

    public static function assertExecutionId(int $id, string $parameterName = 'executionId'): void
    {
        if ($id < self::MIN_EXECUTION_ID) {
            throw new InvalidArgumentException("{$parameterName} must be >= ".self::MIN_EXECUTION_ID.'.');
        }
    }

    public static function assertListLimit(int $limit): void
    {
        if ($limit < 1 || $limit > self::MAX_LIST_LIMIT) {
            throw new InvalidArgumentException('limit must be between 1 and '.self::MAX_LIST_LIMIT.'.');
        }
    }
}
