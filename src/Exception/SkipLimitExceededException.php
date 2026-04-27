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

use Throwable;

class SkipLimitExceededException extends BatchException
{
    /**
     * @param list<Throwable> $skippedExceptions
     */
    public function __construct(
        private readonly int $skipLimit,
        string $message = '',
        ?Throwable $previous = null,
        private readonly array $skippedExceptions = [],
    ) {
        parent::__construct(
            '' !== $message ? $message : "Skip limit of {$skipLimit} exceeded",
            0,
            $previous,
        );
    }

    public function getSkipLimit(): int
    {
        return $this->skipLimit;
    }

    /**
     * @return list<Throwable>
     */
    public function getSkippedExceptions(): array
    {
        return $this->skippedExceptions;
    }
}
