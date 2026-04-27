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

namespace Lemric\BatchProcessing\Domain;

use Stringable;

/**
 * Immutable value object representing the exit status of a Job or Step.
 *
 * The exit code is an arbitrary identifier, the description is a free form message.
 */
final class ExitStatus implements Stringable
{
    public const string COMPLETED_CODE = 'COMPLETED';

    public const string EXECUTING_CODE = 'EXECUTING';

    public const string FAILED_CODE = 'FAILED';

    public const string NOOP_CODE = 'NOOP';

    public const string STOPPED_CODE = 'STOPPED';

    public const string UNKNOWN_CODE = 'UNKNOWN';

    public static ExitStatus $COMPLETED;

    public static ExitStatus $EXECUTING;

    public static ExitStatus $FAILED;

    public static ExitStatus $NOOP;

    public static ExitStatus $STOPPED;

    public static ExitStatus $UNKNOWN;

    public function __construct(
        public string $exitCode,
        public string $exitDescription = '',
    ) {
    }

    public function __toString(): string
    {
        return '' === $this->exitDescription
            ? $this->exitCode
            : $this->exitCode.': '.$this->exitDescription;
    }

    public function addExitDescription(string $description): self
    {
        if ('' === $description || $description === $this->exitDescription) {
            return $this;
        }

        $combined = '' === $this->exitDescription
            ? $description
            : $this->exitDescription.'; '.$description;

        return new self($this->exitCode, $combined);
    }

    public function and(self $other): self
    {
        if ($this->compareTo($other) < 0) {
            return $other->addExitDescription($this->exitDescription);
        }

        return $this->addExitDescription($other->exitDescription);
    }

    public function compareTo(self $other): int
    {
        return $this->severity() <=> $other->severity();
    }

    public function getExitCode(): string
    {
        return $this->exitCode;
    }

    public function getExitDescription(): string
    {
        return $this->exitDescription;
    }

    public function isRunning(): bool
    {
        return self::EXECUTING_CODE === $this->exitCode || self::UNKNOWN_CODE === $this->exitCode;
    }

    public function replaceExitCode(string $code): self
    {
        return new self($code, $this->exitDescription);
    }

    private function severity(): int
    {
        return match ($this->exitCode) {
            self::UNKNOWN_CODE => 0,
            self::EXECUTING_CODE => 1,
            self::NOOP_CODE => 2,
            self::COMPLETED_CODE => 3,
            self::STOPPED_CODE => 4,
            self::FAILED_CODE => 5,
            default => 3,
        };
    }
}

ExitStatus::$UNKNOWN = new ExitStatus(ExitStatus::UNKNOWN_CODE);
ExitStatus::$EXECUTING = new ExitStatus(ExitStatus::EXECUTING_CODE);
ExitStatus::$COMPLETED = new ExitStatus(ExitStatus::COMPLETED_CODE);
ExitStatus::$NOOP = new ExitStatus(ExitStatus::NOOP_CODE);
ExitStatus::$FAILED = new ExitStatus(ExitStatus::FAILED_CODE);
ExitStatus::$STOPPED = new ExitStatus(ExitStatus::STOPPED_CODE);
