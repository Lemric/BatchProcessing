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

namespace Lemric\BatchProcessing\Testing;

use PHPUnit\Framework\Assert;

use const FILE_IGNORE_NEW_LINES;

/**
 * File comparison assertions for batch output verification.
 */
final class AssertFile
{
    /**
     * Assert that a file contains the given string.
     */
    public static function assertFileContains(string $filePath, string $needle, string $message = ''): void
    {
        Assert::assertFileExists($filePath);
        $contents = file_get_contents($filePath);
        Assert::assertIsString($contents);
        Assert::assertStringContainsString(
            $needle,
            $contents,
            $message ?: "File {$filePath} does not contain expected string.",
        );
    }

    /**
     * Assert that two files have identical contents.
     */
    public static function assertFileEquals(string $expected, string $actual, string $message = ''): void
    {
        Assert::assertFileExists($expected, $message ?: "Expected file does not exist: {$expected}");
        Assert::assertFileExists($actual, $message ?: "Actual file does not exist: {$actual}");
        $contents = file_get_contents($actual);
        Assert::assertIsString($contents);
        Assert::assertStringEqualsFile(
            $expected,
            $contents,
            $message ?: "File contents differ between {$expected} and {$actual}",
        );
    }

    /**
     * Assert that a file has exactly N lines.
     */
    public static function assertLineCount(string $filePath, int $expectedCount, string $message = ''): void
    {
        Assert::assertFileExists($filePath);
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        Assert::assertIsArray($lines);
        Assert::assertCount(
            $expectedCount,
            $lines,
            $message ?: "Expected {$expectedCount} lines in {$filePath}, got ".count($lines),
        );
    }
}
