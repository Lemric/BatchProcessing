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

/**
 * Redacts likely secrets and long operational noise from strings persisted in the DB or logs.
 */
final class SensitiveDataSanitizer
{
    private const int MAX_PERSISTED_LENGTH = 4000;

    /**
     * @var list<non-empty-string>
     */
    private const array PATTERNS = [
        // Key=value or key: value style
        '/(?i)\b(password|passwd|pwd|token|secret|api[_-]?key|access[_-]?key|bearer|authorization|client[_-]?secret)\s*[:=]\s*\S+/u',
        // Connection strings
        '/(?i)\b(?:mysql|mysqli|pgsql|postgres(?:ql)?|sqlsrv|oci|sqlite|jdbc):[^\s]+/u',
    ];

    public static function sanitize(string $text): string
    {
        if ('' === $text) {
            return '';
        }
        $out = $text;
        foreach (self::PATTERNS as $pattern) {
            $out = preg_replace($pattern, '[REDACTED]', $out) ?? $out;
        }
        if (mb_strlen($out) > self::MAX_PERSISTED_LENGTH) {
            $out = mb_substr($out, 0, self::MAX_PERSISTED_LENGTH).'…[truncated]';
        }

        return $out;
    }
}
