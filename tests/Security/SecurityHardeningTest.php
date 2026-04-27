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

namespace Lemric\BatchProcessing\Tests\Security;

use InvalidArgumentException;
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Exception\JobExecutionException;
use Lemric\BatchProcessing\Security\{AsyncJobMessageSigner, CliInputBounds, RedisKeyValidator, SensitiveDataSanitizer, SqlIdentifierValidator, UnsafeSqlQueryFragmentValidator};
use PHPUnit\Framework\TestCase;

final class SecurityHardeningTest extends TestCase
{
    public function testCliBounds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CliInputBounds::assertListLimit(99999);
    }

    public function testMessageSignerLegacyV2WithoutParameterBinding(): void
    {
        $issued = time();
        $sig = AsyncJobMessageSigner::sign('sekret', 2, 'legacy', $issued, null);
        AsyncJobMessageSigner::verifyOrFail('sekret', 2, 'legacy', $sig, $issued, 86_400, null);
        self::assertSame($sig, AsyncJobMessageSigner::sign('sekret', 2, 'legacy', $issued, null));
    }

    public function testMessageSignerRejectsEmptySecretOnSign(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AsyncJobMessageSigner::sign('', 1, 'j');
    }

    public function testMessageSignerRejectsEmptySecretOnVerify(): void
    {
        $this->expectException(JobExecutionException::class);
        AsyncJobMessageSigner::verifyOrFail('', 1, 'j', 'sig', time(), 3600);
    }

    public function testMessageSignerRejectsStaleIssuedAt(): void
    {
        $this->expectException(JobExecutionException::class);
        $old = time() - 100_000;
        $sig = AsyncJobMessageSigner::sign('sekret', 1, 'j', $old, null);
        AsyncJobMessageSigner::verifyOrFail('sekret', 1, 'j', $sig, $old, 3600, null);
    }

    public function testMessageSignerVerifies(): void
    {
        $issued = time();
        $jobKey = JobParameters::of(['batch' => '1'])->toJobKey();
        $sig = AsyncJobMessageSigner::sign('sekret', 5, 'myJob', $issued, $jobKey);
        AsyncJobMessageSigner::verifyOrFail('sekret', 5, 'myJob', $sig, $issued, 86_400, $jobKey);
        $this->expectException(JobExecutionException::class);
        AsyncJobMessageSigner::verifyOrFail('sekret', 5, 'myJob', 'wrong', $issued, 86_400, $jobKey);
    }

    public function testRedisKeyValidatorRejectsUnsafe(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RedisKeyValidator::assertSafeKey("key\ninjection");
    }

    public function testSanitizerRedactsSecrets(): void
    {
        $out = SensitiveDataSanitizer::sanitize('password=secret123 and token="abc"');
        self::assertStringNotContainsString('secret123', $out);
        self::assertStringNotContainsString('abc', $out);
    }

    public function testSqlFragmentValidatorRejectsNulByte(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UnsafeSqlQueryFragmentValidator::assertPagingQueryFragment("SELECT 1\x00", 'test');
    }

    public function testTablePrefixRejectInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SqlIdentifierValidator::assertValidTablePrefix('bad;inject');
    }

    public function testValidTableNameSchemaQualified(): void
    {
        SqlIdentifierValidator::assertValidTableName('public.orders');
        $this->expectException(InvalidArgumentException::class);
        SqlIdentifierValidator::assertValidTableName('a.b.c');
    }
}
