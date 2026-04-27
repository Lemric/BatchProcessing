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

use PDOException;

class RepositoryException extends BatchException
{
    /**
     * Wraps a {@see PDOException} without exposing driver-specific text in the outer message
     * (details remain on {@code $previous} for logging).
     */
    public static function fromPdo(string $operationSummary, PDOException $previous): self
    {
        return new self($operationSummary.' failed.', (int) $previous->getCode(), $previous);
    }
}
