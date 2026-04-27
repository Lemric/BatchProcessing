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

namespace Lemric\BatchProcessing\Job\Flow;

/**
 * Represents the execution status of a flow — similar to ExitStatus but scoped to the flow layer.
 */
final readonly class FlowExecutionStatus
{
    public const string COMPLETED = 'COMPLETED';

    public const string FAILED = 'FAILED';

    public const string STOPPED = 'STOPPED';

    public const string UNKNOWN = 'UNKNOWN';

    public function __construct(
        private string $name,
    ) {
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnd(): bool
    {
        return self::COMPLETED === $this->name;
    }

    public function isFail(): bool
    {
        return self::FAILED === $this->name;
    }

    public function isStop(): bool
    {
        return self::STOPPED === $this->name;
    }

    public static function stopped(): self
    {
        return new self(self::STOPPED);
    }
}
