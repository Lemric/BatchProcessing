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

namespace Lemric\BatchProcessing\Tests\Job;

use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Exception\JobParametersInvalidException;
use Lemric\BatchProcessing\Job\DefaultJobParametersValidator;
use PHPUnit\Framework\TestCase;

final class DefaultJobParametersValidatorTest extends TestCase
{
    public function testAllowsOptionalKeys(): void
    {
        $validator = new DefaultJobParametersValidator(['required'], ['optional']);
        $params = JobParameters::of(['required' => 'val', 'optional' => 'extra']);
        $validator->validate($params);
        self::assertSame('val', $params->getString('required'));
        self::assertSame('extra', $params->getString('optional'));
    }

    public function testEmptyValidatorAcceptsAnything(): void
    {
        $validator = new DefaultJobParametersValidator();
        $params = JobParameters::of(['any' => 'thing']);
        $validator->validate($params);
        self::assertSame('thing', $params->getString('any'));
    }

    public function testRejectsUnknownKeys(): void
    {
        $validator = new DefaultJobParametersValidator(['required'], ['optional']);
        $params = JobParameters::of(['required' => 'val', 'unknown' => 'bad']);

        $this->expectException(JobParametersInvalidException::class);
        $this->expectExceptionMessage('Unknown parameter "unknown"');
        $validator->validate($params);
    }

    public function testThrowsForMissingRequiredKey(): void
    {
        $validator = new DefaultJobParametersValidator(['input.file', 'output.dir']);
        $params = JobParameters::of(['input.file' => '/data/in.csv']);

        $this->expectException(JobParametersInvalidException::class);
        $this->expectExceptionMessage('output.dir');
        $validator->validate($params);
    }

    public function testValidatesRequiredKeys(): void
    {
        $validator = new DefaultJobParametersValidator(['input.file', 'output.dir']);
        $params = JobParameters::of(['input.file' => '/data/in.csv', 'output.dir' => '/data/out']);
        $validator->validate($params); // should not throw
        self::assertSame('/data/out', $params->getString('output.dir'));
    }
}
