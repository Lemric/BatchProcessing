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

/**
 * Represents the runtime status of a Job or Step execution.
 *
 * Status transitions are constrained: a status may
 * only be upgraded - downgrading to a "lower" status is forbidden by {@see upgradeTo()}.
 */
enum BatchStatus: string
{
    case ABANDONED = 'ABANDONED';

    case COMPLETED = 'COMPLETED';

    case FAILED = 'FAILED';

    case STARTED = 'STARTED';

    case STARTING = 'STARTING';

    case STOPPED = 'STOPPED';

    case STOPPING = 'STOPPING';

    public function isGreaterThan(self $other): bool
    {
        return $this->ordinal() > $other->ordinal();
    }

    public function isRunning(): bool
    {
        return self::STARTING === $this
            || self::STARTED === $this
            || self::STOPPING === $this;
    }

    public function isUnsuccessful(): bool
    {
        return self::FAILED === $this || self::ABANDONED === $this;
    }

    public static function max(self $a, self $b): self
    {
        return $a->ordinal() >= $b->ordinal() ? $a : $b;
    }

    /**
     * Numeric severity used to order statuses (highest = most "final").
     */
    public function ordinal(): int
    {
        return match ($this) {
            self::STARTING => 0,
            self::STARTED => 1,
            self::STOPPING => 2,
            self::STOPPED => 3,
            self::FAILED => 4,
            self::COMPLETED => 5,
            self::ABANDONED => 6,
        };
    }

    /**
     * Returns the higher of the two statuses (status may never be downgraded).
     */
    public function upgradeTo(self $newStatus): self
    {
        return $newStatus->ordinal() > $this->ordinal() ? $newStatus : $this;
    }
}
