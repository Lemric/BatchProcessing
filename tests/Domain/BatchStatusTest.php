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

namespace Lemric\BatchProcessing\Tests\Domain;

use Lemric\BatchProcessing\Domain\BatchStatus;
use PHPUnit\Framework\TestCase;

final class BatchStatusTest extends TestCase
{
    public function testIsRunning(): void
    {
        self::assertTrue(BatchStatus::STARTING->isRunning());
        self::assertTrue(BatchStatus::STARTED->isRunning());
        self::assertTrue(BatchStatus::STOPPING->isRunning());
        self::assertFalse(BatchStatus::COMPLETED->isRunning());
        self::assertFalse(BatchStatus::FAILED->isRunning());
    }

    public function testIsUnsuccessful(): void
    {
        self::assertTrue(BatchStatus::FAILED->isUnsuccessful());
        self::assertTrue(BatchStatus::ABANDONED->isUnsuccessful());
        self::assertFalse(BatchStatus::COMPLETED->isUnsuccessful());
    }

    public function testOrdinalOrdering(): void
    {
        self::assertSame(0, BatchStatus::STARTING->ordinal());
        self::assertSame(6, BatchStatus::ABANDONED->ordinal());
        self::assertTrue(BatchStatus::COMPLETED->isGreaterThan(BatchStatus::STARTED));
    }

    public function testUpgradeNeverDowngrades(): void
    {
        $upgraded = BatchStatus::COMPLETED->upgradeTo(BatchStatus::STARTED);
        self::assertSame(BatchStatus::COMPLETED, $upgraded);

        $upgraded = BatchStatus::STARTED->upgradeTo(BatchStatus::FAILED);
        self::assertSame(BatchStatus::FAILED, $upgraded);
    }
}
