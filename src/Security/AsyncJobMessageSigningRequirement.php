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

/**
 * Enforces that asynchronous job hand-off (queue / Messenger) is always protected by HMAC.
 */
final class AsyncJobMessageSigningRequirement
{
    /**
     * @throws InvalidArgumentException when async execution is enabled but no signing secret is configured
     */
    public static function assertSecretConfiguredForAsync(bool $asyncEnabled, mixed $messageSecret, string $contextLabel): void
    {
        if (!$asyncEnabled) {
            return;
        }
        if (!is_string($messageSecret) || '' === mb_trim($messageSecret)) {
            throw new InvalidArgumentException("When asynchronous batch execution is enabled ({$contextLabel}), message_secret must be a non-empty string. ".'Set BATCH_ASYNC_MESSAGE_SECRET (Laravel), batch_processing.async.message_secret, or batch_processing.async_launcher.message_secret (Symfony).');
        }
    }

    /**
     * Call sites that always represent async dispatch (e.g. queue dispatcher) must provide a secret.
     *
     * @throws InvalidArgumentException
     */
    public static function assertSecretForDispatch(mixed $messageSecret, string $contextLabel): void
    {
        self::assertSecretConfiguredForAsync(true, $messageSecret, $contextLabel);
    }
}
