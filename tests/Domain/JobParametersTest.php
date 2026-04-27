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

use DateTimeImmutable;
use InvalidArgumentException;
use Lemric\BatchProcessing\Domain\{JobParameter, JobParameters};
use PHPUnit\Framework\TestCase;
use stdClass;

final class JobParametersTest extends TestCase
{
    public function testIdentifyingParametersAreUsedInJobKey(): void
    {
        $a = JobParameters::of(['run.id' => 1, 'date' => '2024-01-01']);
        $b = JobParameters::of(['date' => '2024-01-01', 'run.id' => 1]);

        self::assertSame($a->toJobKey(), $b->toJobKey(), 'Job key must be order-independent.');
    }

    public function testNonIdentifyingParametersAreExcludedFromKey(): void
    {
        $a = JobParameters::of([
            'run.id' => JobParameter::ofLong('run.id', 1, identifying: true),
            'note' => JobParameter::ofString('note', 'hello', identifying: false),
        ]);
        $b = JobParameters::of([
            'run.id' => JobParameter::ofLong('run.id', 1, identifying: true),
        ]);

        self::assertSame($a->toJobKey(), $b->toJobKey());
    }

    public function testOfBuildsParametersFromScalars(): void
    {
        $params = JobParameters::of([
            'name' => 'orders',
            'count' => 42,
            'price' => 9.99,
            'when' => new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        ]);

        self::assertSame('orders', $params->getString('name'));
        self::assertSame(42, $params->getLong('count'));
        self::assertSame(9.99, $params->getDouble('price'));
        self::assertEquals(new DateTimeImmutable('2024-01-01T00:00:00+00:00'), $params->getDate('when'));
    }

    public function testRejectsUnsupportedTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JobParameters::of(['bad' => new stdClass()]);
    }
}
