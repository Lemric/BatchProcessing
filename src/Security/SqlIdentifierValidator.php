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

use function count;

/**
 * Validates SQL identifier-like strings used for table names and prefixes.
 *
 * Only unquoted ASCII identifiers are accepted (letters, digits, underscore).
 */
final class SqlIdentifierValidator
{
    private const string IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public static function assertValidIdentifier(string $name, string $context = 'identifier'): void
    {
        if ('' === $name) {
            throw new InvalidArgumentException("SQL {$context} must not be empty.");
        }
        if (!preg_match(self::IDENTIFIER_PATTERN, $name)) {
            throw new InvalidArgumentException("Invalid SQL {$context}: only [A-Za-z_][A-Za-z0-9_]* is allowed.");
        }
    }

    /**
     * PostgreSQL sequence name, optionally schema-qualified (e.g. {@code myschema.myseq}).
     */
    public static function assertValidPostgresSequenceName(string $name): void
    {
        self::assertQualifiedIdentifierChain($name, 'PostgreSQL sequence name', 2);
    }

    /**
     * SQL table reference: single identifier or {@code schema.table} (at most one dot).
     */
    public static function assertValidTableName(string $name, string $context = 'table name'): void
    {
        self::assertQualifiedIdentifierChain($name, $context, 2);
    }

    public static function assertValidTablePrefix(string $prefix): void
    {
        if ('' === $prefix) {
            throw new InvalidArgumentException('table_prefix must not be empty.');
        }
        if (!preg_match(self::IDENTIFIER_PATTERN, $prefix)) {
            throw new InvalidArgumentException('table_prefix must match pattern ^[A-Za-z_][A-Za-z0-9_]*$ (got invalid characters).');
        }
    }

    /**
     * @param positive-int $maxParts
     */
    private static function assertQualifiedIdentifierChain(string $name, string $context, int $maxParts): void
    {
        if ('' === $name) {
            throw new InvalidArgumentException("{$context} must not be empty.");
        }
        $parts = explode('.', $name);
        if (count($parts) > $maxParts) {
            throw new InvalidArgumentException("{$context} may have at most ".($maxParts - 1).' qualifier segment(s).');
        }
        foreach ($parts as $part) {
            self::assertValidIdentifier($part, $context);
        }
    }
}
