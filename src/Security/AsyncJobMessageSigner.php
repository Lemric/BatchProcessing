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

namespace Lemric\BatchProcessing\Security;

use InvalidArgumentException;
use Lemric\BatchProcessing\Exception\JobExecutionException;
use SensitiveParameter;

/**
 * HMAC-SHA256 over the async job message payload to detect tampering on the queue transport.
 *
 * The signed payload includes a Unix issue timestamp to bound replay of captured messages.
 */
final class AsyncJobMessageSigner
{
    /** Default maximum queue transit / deferral window (7 days). */
    public const int DEFAULT_MAX_MESSAGE_AGE_SECONDS = 604_800;

    /** Allowed clock skew for {@code issuedAt} slightly in the future (seconds). */
    private const int ISSUED_AT_FUTURE_LEEWAY_SECONDS = 300;

    /**
     * @param string|null $parametersJobKey {@see JobParameters::toJobKey()} for the execution being dispatched.
     *                                      When null, the legacy v2 payload (no parameter binding) is used for
     *                                      backward compatibility with in-flight queue messages.
     */
    public static function sign(
        #[SensitiveParameter] string $secret,
        int $jobExecutionId,
        string $jobName,
        ?int $issuedAt = null,
        ?string $parametersJobKey = null,
    ): string {
        if ('' === $secret || '' === mb_trim($secret)) {
            throw new InvalidArgumentException('Cannot sign async job message: message secret must be non-empty.');
        }
        $issuedAt ??= time();
        if ($issuedAt < 1) {
            throw new InvalidArgumentException('Cannot sign async job message: issuedAt must be a positive Unix timestamp.');
        }
        $payload = null === $parametersJobKey
            ? self::payloadV2($jobExecutionId, $jobName, $issuedAt)
            : self::payloadV3($jobExecutionId, $jobName, $issuedAt, $parametersJobKey);

        return self::base64UrlEncode(hash_hmac('sha256', $payload, $secret, true));
    }

    /**
     * @param string|null $signature from the message; null when signing was disabled
     */
    /**
     * @param string|null $parametersJobKey fingerprint from the message; null selects legacy v2 verification only
     */
    public static function verifyOrFail(
        #[SensitiveParameter] string $secret,
        int $jobExecutionId,
        string $jobName,
        ?string $signature,
        int $messageIssuedAt,
        int $maxAgeSeconds = self::DEFAULT_MAX_MESSAGE_AGE_SECONDS,
        ?string $parametersJobKey = null,
    ): void {
        if ('' === $secret || '' === mb_trim($secret)) {
            throw new JobExecutionException('Async job messages must be verified with a non-empty message secret. Configure BATCH_ASYNC_MESSAGE_SECRET, batch_processing.async.message_secret, or batch_processing.async_launcher.message_secret.');
        }
        if (null === $signature || '' === $signature) {
            throw new JobExecutionException('Async job message is missing an HMAC signature.');
        }
        if ($maxAgeSeconds < 60) {
            throw new JobExecutionException('Async job message TTL is misconfigured (must be at least 60 seconds).');
        }
        self::assertFreshTimestamp($messageIssuedAt, $maxAgeSeconds);
        $expected = self::sign($secret, $jobExecutionId, $jobName, $messageIssuedAt, $parametersJobKey);
        if (!hash_equals($expected, $signature)) {
            throw new JobExecutionException('Async job message signature is invalid (possible tampering).');
        }
    }

    private static function assertFreshTimestamp(int $issuedAt, int $maxAgeSeconds): void
    {
        if ($issuedAt < 1) {
            throw new JobExecutionException('Async job message has an invalid issue timestamp.');
        }
        $now = time();
        if ($issuedAt > $now + self::ISSUED_AT_FUTURE_LEEWAY_SECONDS) {
            throw new JobExecutionException('Async job message issue timestamp is too far in the future.');
        }
        if ($now - $issuedAt > $maxAgeSeconds) {
            throw new JobExecutionException('Async job message has expired (maximum age exceeded). Stale or replayed delivery is not accepted.');
        }
    }

    private static function base64UrlEncode(string $binary): string
    {
        return mb_rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private static function payloadV2(int $jobExecutionId, string $jobName, int $issuedAt): string
    {
        return 'v2:'.$jobExecutionId."\0".$jobName."\0".$issuedAt;
    }

    private static function payloadV3(int $jobExecutionId, string $jobName, int $issuedAt, string $parametersJobKey): string
    {
        return 'v3:'.$jobExecutionId."\0".$jobName."\0".$issuedAt."\0".$parametersJobKey;
    }
}
