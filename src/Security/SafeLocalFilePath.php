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

use Lemric\BatchProcessing\Exception\NonTransientResourceException;

use const DIRECTORY_SEPARATOR;

/**
 * Ensures file paths point to local files (no stream wrappers) and optionally jail to a base directory.
 */
final class SafeLocalFilePath
{
    /**
     * @throws NonTransientResourceException
     */
    public static function assertReadableLocalFile(string $path, ?string $allowedBaseDirectory = null): void
    {
        if ('' === $path) {
            throw new NonTransientResourceException('File path must not be empty.');
        }
        self::rejectStreamWrappers($path);
        $real = realpath($path);
        if (false === $real || !is_file($real) || !is_readable($real)) {
            throw new NonTransientResourceException("File is not a readable local file: {$path}");
        }
        if (null !== $allowedBaseDirectory && '' !== $allowedBaseDirectory) {
            $baseReal = realpath($allowedBaseDirectory);
            if (false === $baseReal || !is_dir($baseReal)) {
                throw new NonTransientResourceException('Allowed base directory does not exist or is not a directory.');
            }
            $baseWithSep = mb_rtrim($baseReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $realWithSep = mb_rtrim($real, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (!str_starts_with($realWithSep, $baseWithSep) && $real !== $baseReal) {
                throw new NonTransientResourceException('File path escapes the allowed base directory.');
            }
        }
    }

    /**
     * Validates a path that will be written to (file may not exist yet).
     *
     * @throws NonTransientResourceException
     */
    public static function assertWritableLocalPath(string $path, ?string $allowedBaseDirectory = null): void
    {
        if ('' === $path) {
            throw new NonTransientResourceException('File path must not be empty.');
        }
        self::rejectStreamWrappers($path);
        $dir = dirname($path);
        $dirReal = realpath($dir);
        if (false === $dirReal || !is_dir($dirReal) || !is_writable($dirReal)) {
            throw new NonTransientResourceException("Target directory is not writable: {$dir}");
        }
        if (null !== $allowedBaseDirectory && '' !== $allowedBaseDirectory) {
            $baseReal = realpath($allowedBaseDirectory);
            if (false === $baseReal) {
                throw new NonTransientResourceException('Allowed base directory does not exist.');
            }
            $baseWithSep = mb_rtrim($baseReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $dirWithSep = mb_rtrim($dirReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (!str_starts_with($dirWithSep, $baseWithSep) && $dirReal !== $baseReal) {
                throw new NonTransientResourceException('File path escapes the allowed base directory.');
            }
        }
    }

    private static function rejectStreamWrappers(string $path): void
    {
        if (1 === preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            throw new NonTransientResourceException('Stream wrapper URLs are not allowed as file paths.');
        }
    }
}
