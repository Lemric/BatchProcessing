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
 * Restricts Redis key names to a conservative ASCII subset to avoid accidental injection of
 * control characters, newlines, or spaces into command streams when keys originate from config.
 */
final class RedisKeyValidator
{
    private const int MAX_KEY_LENGTH = 512;

    /** Conservative pattern: alnum, colon, underscore, hyphen, dot, at (stream tags). */
    private const string SAFE_KEY_PATTERN = '/^[A-Za-z0-9_:\-.@]+$/';

    public static function assertSafeKey(string $key, string $context = 'Redis key'): void
    {
        if ('' === $key) {
            throw new InvalidArgumentException("{$context} must not be empty.");
        }
        if (mb_strlen($key) > self::MAX_KEY_LENGTH) {
            throw new InvalidArgumentException("{$context} exceeds maximum length of ".self::MAX_KEY_LENGTH.'.');
        }
        if (1 !== preg_match(self::SAFE_KEY_PATTERN, $key)) {
            throw new InvalidArgumentException("{$context} must match [A-Za-z0-9_:\\-.@]+ only.");
        }
    }
}
