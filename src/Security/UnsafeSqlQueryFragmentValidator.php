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
 * Rejects obviously dangerous constructs in user-assembled SQL fragments used with PDO::prepare().
 *
 * Prepared statements only parameterize bound values — embedded SQL text must still be validated.
 * This is defense-in-depth only: it does not replace parameterized queries or trusted SQL sources.
 */
final class UnsafeSqlQueryFragmentValidator
{
    /**
     * Validates a full SELECT-style statement body before LIMIT/OFFSET are appended (e.g. {@see PaginatedPdoItemReader}).
     */
    public static function assertPaginatedStatementSql(string $sql, string $role): void
    {
        self::rejectDangerousPatterns($sql, $role);
    }

    public static function assertPagingQueryFragment(string $fragment, string $role): void
    {
        self::rejectDangerousPatterns($fragment, $role);
    }

    public static function assertPdoSelectLikeStatement(string $sql, string $role): void
    {
        self::rejectDangerousPatterns($sql, $role);
        $trimmed = mb_ltrim($sql);
        if ('' === $trimmed) {
            throw new InvalidArgumentException("Unsafe SQL {$role}: statement must not be empty.");
        }
        $upper = mb_strtoupper($trimmed);
        foreach (['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE', 'REPLACE', 'MERGE'] as $kw) {
            if (str_starts_with($upper, $kw.' ') || str_starts_with($upper, $kw."\t")
                || str_starts_with($upper, $kw."\n") || str_starts_with($upper, $kw."\r")) {
                throw new InvalidArgumentException("Unsafe SQL {$role}: disallowed statement type ({$kw}).");
            }
        }
    }

    /**
     * Defense-in-depth for user-supplied SQL strings passed to {@see \PDO::prepare()} (e.g. readers).
     * Rejects obvious DML/DDL prefixes after trim; still not a substitute for static, reviewed SQL.
     */
    /**
     * Writer SQL (INSERT/UPDATE/DELETE): rejects stacked statements and obvious exfiltration; values must still use binds.
     */
    public static function assertPdoWriterStatement(string $sql, string $role): void
    {
        self::rejectDangerousPatterns($sql, $role);
    }

    private static function rejectDangerousPatterns(string $sql, string $role): void
    {
        if (str_contains($sql, "\0")) {
            throw new InvalidArgumentException("Unsafe SQL {$role}: NUL bytes are not allowed.");
        }
        if (1 === preg_match('/[\x{0085}\x{2028}\x{2029}]/u', $sql)) {
            throw new InvalidArgumentException("Unsafe SQL {$role}: Unicode line/paragraph separators are not allowed.");
        }
        $lower = mb_strtolower($sql);
        if (str_contains($sql, ';')) {
            throw new InvalidArgumentException("Unsafe SQL {$role}: multiple statements (;) are not allowed.");
        }
        if (str_contains($sql, '--') || str_contains($sql, '/*')) {
            throw new InvalidArgumentException("Unsafe SQL {$role}: SQL comments are not allowed.");
        }
        foreach ([
            ' union ', ' into outfile ', ' load_file', 'into dumpfile', ' outfile ', 'exec ', 'execute ', 'call mysql.', 'benchmark(',
            ' information_schema', ' pg_sleep', ' waitfor ', ' delay ', ' sleep(', ' xp_cmdshell', ' get_lock(',
            ' release_lock(', ' version(', '@@version',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                throw new InvalidArgumentException("Unsafe SQL {$role}: disallowed keyword or construct.");
            }
        }
        if (1 === preg_match('/\bunion\b/i', $sql)) {
            throw new InvalidArgumentException("Unsafe SQL {$role}: UNION is not allowed.");
        }
    }
}
