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

namespace Lemric\BatchProcessing\Tests\Registry;

use Lemric\BatchProcessing\Attribute\BatchJob;
use Lemric\BatchProcessing\Domain\{JobExecution, JobParameters};
use Lemric\BatchProcessing\Job\JobInterface;
use Lemric\BatchProcessing\Registry\{AttributeJobScanner, InMemoryJobRegistry};
use PHPUnit\Framework\TestCase;
use stdClass;

use function assert;

#[BatchJob(name: 'fixture.attribute.job')]
final class AttributeJobScannerTestJobFixture implements JobInterface
{
    public function execute(JobExecution $jobExecution): void
    {
    }

    public function getName(): string
    {
        return 'fixture.attribute.job';
    }

    public function isRestartable(): bool
    {
        return true;
    }

    public function validateParameters(JobParameters $parameters): void
    {
    }
}

final class AttributeJobScannerTest extends TestCase
{
    public function testScanRegistersAnnotatedJobsLazily(): void
    {
        $registry = new InMemoryJobRegistry();
        $created = 0;
        $factory = static function (string $class) use (&$created): JobInterface {
            ++$created;
            $instance = new $class();
            assert($instance instanceof JobInterface);

            return $instance;
        };

        $count = AttributeJobScanner::scan(
            [AttributeJobScannerTestJobFixture::class, stdClass::class],
            $factory,
            $registry,
        );

        self::assertSame(1, $count);
        self::assertSame(0, $created, 'Factory must not be invoked before getJob().');
        self::assertTrue($registry->hasJob('fixture.attribute.job'));

        $job = $registry->getJob('fixture.attribute.job');
        self::assertSame(1, $created);
        self::assertSame('fixture.attribute.job', $job->getName());
    }
}
