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

namespace Lemric\BatchProcessing\Step;

use InvalidArgumentException;
use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\StepContribution;
use Lemric\BatchProcessing\Exception\StepExecutionException;
use Throwable;

use function function_exists;
use function is_resource;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_select;
use function stream_set_blocking;
use function usleep;

use const INF;
use const SIG_BLOCK;
use const SIG_SETMASK;
use const SIGHUP;
use const SIGINT;
use const SIGQUIT;
use const SIGTERM;

// SIG* / SIG_BLOCK / SIG_SETMASK constants come from ext-pcntl. They are referenced only
// from within guarded helpers (see blockTerminationSignals / restoreSignalMask) so the
// extension is an optional, suggested dependency — not a hard requirement.

/**
 * Built-in tasklet that executes an external system command.
 */
final class SystemCommandTasklet implements TaskletInterface
{
    /** @var list<string> */
    private readonly array $command;

    /** @var array<string, string>|null */
    private readonly ?array $environmentParams;

    private readonly int $outputLimitBytes;

    private readonly float $timeoutSeconds;

    private readonly ?string $workingDirectory;

    /** Maximum bytes of stdout/stderr captured per stream (8 MiB by default). */
    private const int DEFAULT_OUTPUT_LIMIT_BYTES = 8 * 1024 * 1024;

    /** Hard cap on length of any single argument in bytes (128 KiB). */
    private const int MAX_ARG_LENGTH_BYTES = 128 * 1024;

    /** Hard cap on number of arguments (defends against ARG_MAX / E2BIG abuse). */
    private const int MAX_ARGV_COUNT = 4096;

    /** Hard cap on the number of environment variables. */
    private const int MAX_ENV_COUNT = 1024;

    /** Hard cap on per-env-var value length in bytes (128 KiB). */
    private const int MAX_ENV_VALUE_BYTES = 128 * 1024;

    /** Hard cap on the total argv byte size (1 MiB; well below typical ARG_MAX of 2 MiB). */
    private const int MAX_TOTAL_ARGV_BYTES = 1024 * 1024;

    /** Per-iteration read budget in bytes — bounds peak memory from a fast-producing child. */
    private const int READ_CHUNK_BYTES = 64 * 1024;

    /** Grace period (microseconds) between SIGTERM and SIGKILL during a forced shutdown. */
    private const int TERMINATION_GRACE_MICROS = 1_000_000;

    /**
     * @param list<string>               $command                    program followed by its arguments. MUST NOT be empty.
     * @param string|null                $workingDirectory           absolute or relative existing directory, or null
     * @param float|null                 $timeoutSeconds             wall-clock timeout in seconds. {@code null} disables it.
     * @param array<string, string>|null $environmentParams          whitelisted env vars; null = inherit current env
     * @param int                        $outputLimitBytes           max bytes captured per stream (>= 1024)
     * @param list<string>|null          $allowedExecutableBasenames optional allow-list of executable basenames (e.g. {@code ['gzip','awk']}); null disables
     * @param bool                       $requireExecutableAllowList when true, {@code $allowedExecutableBasenames} must be a non-empty list (production hardening)
     */
    public function __construct(
        array $command,
        ?string $workingDirectory = null,
        ?float $timeoutSeconds = 60.0,
        ?array $environmentParams = null,
        int $outputLimitBytes = self::DEFAULT_OUTPUT_LIMIT_BYTES,
        ?array $allowedExecutableBasenames = null,
        bool $requireExecutableAllowList = false,
    ) {
        if ($requireExecutableAllowList && (null === $allowedExecutableBasenames || [] === $allowedExecutableBasenames)) {
            throw new InvalidArgumentException('When requireExecutableAllowList is true, allowedExecutableBasenames must be a non-empty list.');
        }
        $this->command = self::validateCommand($command);
        self::assertExecutableAllowList($this->command, $allowedExecutableBasenames);
        $this->workingDirectory = self::validateWorkingDirectory($workingDirectory);
        $this->environmentParams = self::validateEnvironment($environmentParams);
        $this->timeoutSeconds = self::validateTimeout($timeoutSeconds);
        $this->outputLimitBytes = self::validateOutputLimit($outputLimitBytes);
    }

    public function execute(StepContribution $contribution, ChunkContext $chunkContext): RepeatStatus
    {
        if (!function_exists('proc_open')) {
            throw new StepExecutionException('proc_open is disabled in the current PHP configuration.');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $io = new class {
            public mixed $process = null;

            /** @var array<int, mixed> */
            public array $pipes = [];
        };

        // Block job-control / termination signals for the critical section so that
        // cleanup is atomic. Without this, a SIGINT delivered between proc_open and
        // the finally-block could orphan the child and leak file descriptors
        // (CWE-364: Signal Handler Race Condition; CWE-404: Improper Resource Shutdown).
        $previousSignalMask = self::blockTerminationSignals();

        // Last-line-of-defence cleanup in case of a fatal error / unrecoverable
        // condition that prevents the finally block from running.
        $shutdownCallback = static function () use ($io): void {
            foreach ($io->pipes as $p) {
                if (is_resource($p)) {
                    @fclose($p);
                }
            }
            if (is_resource($io->process)) {
                @proc_terminate($io->process, 9); // SIGKILL
                @proc_close($io->process);
            }
        };
        register_shutdown_function($shutdownCallback);

        try {
            // proc_open with an ARRAY $command bypasses the shell entirely (no /bin/sh -c).
            // This is the cornerstone of the injection-proof design.
            $io->process = @proc_open(
                $this->command,
                $descriptors,
                $io->pipes,
                $this->workingDirectory,
                $this->environmentParams,
                ['bypass_shell' => true],
            );

            if (!is_resource($io->process)) {
                throw new StepExecutionException(sprintf('Failed to start system command "%s".', self::redactProgram($this->command[0])));
            }

            try {
                // We do not write to stdin; close it immediately so child processes that read
                // stdin (e.g. cat) get EOF and don't block forever.
                if (isset($io->pipes[0]) && is_resource($io->pipes[0])) {
                    fclose($io->pipes[0]);
                    unset($io->pipes[0]);
                }

                // Make the pipes non-blocking so stream_select can drive the loop.
                foreach ([1, 2] as $fd) {
                    if (isset($io->pipes[$fd]) && is_resource($io->pipes[$fd])) {
                        stream_set_blocking($io->pipes[$fd], false);
                    }
                }

                [$stdout, $stderr, $exitCode] = $this->pumpAndWait($io->process, $io->pipes);
            } catch (StepExecutionException $e) {
                // Already a domain-level failure; rethrow after cleanup runs.
                throw $e;
            } catch (Throwable $e) {
                // Any unexpected error during the I/O loop must NOT leak the process.
                // Wrap it in a redacted StepExecutionException — never expose raw command/args.
                throw new StepExecutionException(sprintf('System command "%s" aborted due to an internal error: %s', self::redactProgram($this->command[0]), self::truncateForMessage($e->getMessage())), 0, $e);
            } finally {
                // Defensive cleanup — ensure no FD or process is leaked even on exception.
                foreach ($io->pipes as $pipe) {
                    if (is_resource($pipe)) {
                        @fclose($pipe);
                    }
                }
                $io->pipes = [];
                if (is_resource($io->process)) {
                    $status = @proc_get_status($io->process);
                    if (true === $status['running']) {
                        $this->terminateProcess($io->process);
                    }
                    @proc_close($io->process);
                    $io->process = null;
                }
            }

            if (0 !== $exitCode) {
                throw new StepExecutionException(sprintf('System command "%s" failed with exit code %d. stderr: %s', self::redactProgram($this->command[0]), $exitCode, self::truncateForMessage('' !== $stderr ? $stderr : $stdout)));
            }
        } finally {
            // Restore the signal mask so the rest of the application keeps its semantics.
            if (null !== $previousSignalMask) {
                self::restoreSignalMask($previousSignalMask);
            }
            // The shutdown callback is no longer needed once we get here under normal flow.
            // PHP has no public API to unregister it, so we neutralise it by clearing the
            // captured references (the closure now sees null/empty and is a no-op).
            $io->process = null;
            $io->pipes = [];
        }

        return RepeatStatus::FINISHED;
    }

    private static function appendBounded(string $current, string $chunk, int $limit): string
    {
        // Use byte-level (not multibyte) accounting — an attacker controlling the child's
        // output could otherwise emit multibyte sequences to bypass the byte budget.
        $remaining = $limit - mb_strlen($current);
        if ($remaining <= 0) {
            return $current;
        }
        if (mb_strlen($chunk) > $remaining) {
            $chunk = mb_substr($chunk, 0, $remaining);
        }

        return $current.$chunk;
    }

    /**
     * @param list<string>      $command
     * @param list<string>|null $allowed
     */
    private static function assertExecutableAllowList(array $command, ?array $allowed): void
    {
        if (null === $allowed || [] === $allowed) {
            return;
        }
        $program = $command[0];
        $base = basename($program);
        foreach ($allowed as $entry) {
            if ($base === $entry || $program === $entry) {
                return;
            }
        }
        throw new InvalidArgumentException(sprintf('Executable "%s" is not permitted by the allow-list policy.', $base));
    }

    /**
     * Block the most common termination signals for the critical section.
     *
     * @return array<int>|null previous signal mask, or null if pcntl is unavailable
     */
    private static function blockTerminationSignals(): ?array
    {
        if (!function_exists('pcntl_sigprocmask')) {
            return null;
        }
        $previous = [];
        // Build the set defensively — some constants may not exist on every platform.
        $toBlock = array_values(array_filter([
            defined('SIGINT') ? SIGINT : null,
            defined('SIGTERM') ? SIGTERM : null,
            defined('SIGHUP') ? SIGHUP : null,
            defined('SIGQUIT') ? SIGQUIT : null,
        ], static fn ($v): bool => null !== $v));
        if ([] === $toBlock) {
            return null;
        }
        @pcntl_sigprocmask(SIG_BLOCK, $toBlock, $previous);

        if (!is_array($previous)) {
            return [];
        }

        $result = [];
        foreach ($previous as $signal) {
            if (is_int($signal)) {
                $result[] = $signal;
            }
        }

        return $result;
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    /**
     * Drives the I/O loop: reads available output, enforces the wall-clock timeout and
     * terminates the process if the budget is exhausted.
     *
     * @param resource          $process
     * @param array<int, mixed> $pipes
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function pumpAndWait($process, array $pipes): array
    {
        $stdout = '';
        $stderr = '';
        $startedAt = microtime(true);
        $stdoutOpen = isset($pipes[1]);
        $stderrOpen = isset($pipes[2]);
        // Cache exit code at the running→stopped transition. proc_get_status only
        // exposes a valid exitcode on the FIRST call after termination; subsequent
        // calls return -1. Without this cache the exit status would be lost
        // whenever there is any post-exit output to drain.
        $exitCode = -1;
        $exitCaptured = false;

        while (true) {
            $stdoutPipe = $pipes[1] ?? null;
            $stderrPipe = $pipes[2] ?? null;
            $status = proc_get_status($process);
            $isRunning = $status['running'];
            if (!$isRunning && !$exitCaptured) {
                $exitCode = $status['exitcode'];
                $exitCaptured = true;
            }

            // Drain whatever the OS has buffered for us (bounded read to cap peak memory).
            if ($stdoutOpen && is_resource($stdoutPipe)) {
                $chunk = @fread($stdoutPipe, self::READ_CHUNK_BYTES);
                if (is_string($chunk) && '' !== $chunk) {
                    $stdout = self::appendBounded($stdout, $chunk, $this->outputLimitBytes);
                }
                if (feof($stdoutPipe)) {
                    $stdoutOpen = false;
                }
            }
            if ($stderrOpen && is_resource($stderrPipe)) {
                $chunk = @fread($stderrPipe, self::READ_CHUNK_BYTES);
                if (is_string($chunk) && '' !== $chunk) {
                    $stderr = self::appendBounded($stderr, $chunk, $this->outputLimitBytes);
                }
                if (feof($stderrPipe)) {
                    $stderrOpen = false;
                }
            }

            if (!$isRunning && !$stdoutOpen && !$stderrOpen) {
                break;
            }

            // Enforce the wall-clock timeout.
            if (INF !== $this->timeoutSeconds && (microtime(true) - $startedAt) >= $this->timeoutSeconds) {
                $this->terminateProcess($process);
                throw new StepExecutionException(sprintf('System command "%s" exceeded timeout of %.2f seconds and was terminated.', self::redactProgram($this->command[0]), $this->timeoutSeconds));
            }

            // Wait briefly for more data (or until the process exits) without busy-looping.
            $read = [];
            if ($stdoutOpen && is_resource($stdoutPipe)) {
                $read[] = $stdoutPipe;
            }
            if ($stderrOpen && is_resource($stderrPipe)) {
                $read[] = $stderrPipe;
            }
            $write = null;
            $except = null;

            if ([] !== $read) {
                @stream_select($read, $write, $except, 0, 100_000);
            } else {
                usleep(50_000);
            }
        }

        return [$stdout, $stderr, $exitCode];
    }

    private static function redactProgram(string $program): string
    {
        // Cross-platform basename: handle both / and \ as separators without depending
        // on the server locale (PHP's basename() ignores \ on POSIX).
        $normalized = str_replace('\\', '/', $program);
        $pos = mb_strrpos($normalized, '/');
        $base = false === $pos ? $normalized : mb_substr($normalized, $pos + 1);

        return '' === $base ? '<command>' : $base;
    }

    /**
     * Restore a previously-saved signal mask.
     *
     * @param array<int> $previous
     */
    private static function restoreSignalMask(array $previous): void
    {
        if (!function_exists('pcntl_sigprocmask')) {
            return;
        }
        $unused = [];
        @pcntl_sigprocmask(SIG_SETMASK, $previous, $unused);
    }

    /**
     * Best-effort graceful then forced termination.
     *
     * @param resource $process
     */
    private function terminateProcess($process): void
    {
        @proc_terminate($process, 15); // SIGTERM
        $waitedMicros = 0;
        while ($waitedMicros < self::TERMINATION_GRACE_MICROS) {
            $status = @proc_get_status($process);
            if (false === $status['running']) {
                return;
            }
            usleep(50_000);
            $waitedMicros += 50_000;
        }
        @proc_terminate($process, 9); // SIGKILL
    }

    private static function truncateForMessage(string $output): string
    {
        $output = mb_trim($output);
        if ('' === $output) {
            return '(no output)';
        }
        if (mb_strlen($output) > 512) {
            $output = mb_substr($output, 0, 512).'…';
        }

        // Strip ANSI/CSI escape sequences first to defend against terminal-injection /
        // log-forging attacks (CWE-117, CWE-150) where a child writes \x1B[...m or
        // \x1B]...; sequences that hijack the operator's terminal.
        $output = (string) preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', $output);
        $output = (string) preg_replace('/\x1B][^\x07\x1B]*(?:\x07|\x1B\\\\)/', '', $output);

        // Strip remaining control characters (except tab/newline) to keep error messages log-safe.
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '?', $output);
    }

    /**
     * @param list<string> $command
     *
     * @return list<string>
     */
    private static function validateCommand(array $command): array
    {
        if ([] === $command) {
            throw new InvalidArgumentException('SystemCommandTasklet requires a non-empty command list.');
        }
        if (count($command) > self::MAX_ARGV_COUNT) {
            throw new InvalidArgumentException(sprintf('Command exceeds the maximum of %d arguments.', self::MAX_ARGV_COUNT));
        }
        // Reject associative arrays — proc_open's array form requires a numerically indexed list.
        $expectedIndex = 0;
        $totalBytes = 0;
        foreach ($command as $index => $arg) {
            if ($index !== $expectedIndex) {
                throw new InvalidArgumentException('Command must be a list (sequential numeric keys starting from 0).');
            }
            ++$expectedIndex;
            if (str_contains($arg, "\0")) {
                // CWE-158 / Null Byte Injection.
                throw new InvalidArgumentException('Command arguments must not contain NUL bytes.');
            }
            $len = mb_strlen($arg);
            if ($len > self::MAX_ARG_LENGTH_BYTES) {
                throw new InvalidArgumentException(sprintf('Command argument #%d exceeds the maximum length of %d bytes.', $index, self::MAX_ARG_LENGTH_BYTES));
            }
            $totalBytes += $len;
            if ($totalBytes > self::MAX_TOTAL_ARGV_BYTES) {
                throw new InvalidArgumentException(sprintf('Total command size exceeds the maximum of %d bytes.', self::MAX_TOTAL_ARGV_BYTES));
            }
        }
        if ('' === $command[0]) {
            throw new InvalidArgumentException('The program name (first element) must be a non-empty string.');
        }
        // Disallow control characters in the program name to prevent crafted argv[0] tricks.
        if (1 === preg_match('/[\x00-\x1F]/', $command[0])) {
            throw new InvalidArgumentException('The program name must not contain control characters.');
        }
        // Reject leading '-' to prevent argv[0]-as-flag confusion (e.g. wrappers parsing argv[0]).
        if ('-' === $command[0][0]) {
            throw new InvalidArgumentException('The program name must not start with "-".');
        }

        return $command;
    }

    /**
     * @param array<string, string>|null $env
     *
     * @return array<string, string>|null
     */
    private static function validateEnvironment(?array $env): ?array
    {
        if (null === $env) {
            return null;
        }
        if (count($env) > self::MAX_ENV_COUNT) {
            throw new InvalidArgumentException(sprintf('Too many environment variables (max %d).', self::MAX_ENV_COUNT));
        }
        $clean = [];
        foreach ($env as $key => $value) {
            if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                throw new InvalidArgumentException(sprintf('Environment variable name "%s" is invalid (must match [A-Za-z_][A-Za-z0-9_]*).', $key));
            }
            if (str_contains($value, "\0")) {
                throw new InvalidArgumentException(sprintf('Environment variable "%s" must not contain NUL bytes.', $key));
            }
            if (mb_strlen($value) > self::MAX_ENV_VALUE_BYTES) {
                throw new InvalidArgumentException(sprintf('Environment variable "%s" value exceeds the maximum length of %d bytes.', $key, self::MAX_ENV_VALUE_BYTES));
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    private static function validateOutputLimit(int $limit): int
    {
        if ($limit < 1024) {
            throw new InvalidArgumentException('Output limit must be at least 1024 bytes.');
        }

        return $limit;
    }

    private static function validateTimeout(?float $timeout): float
    {
        if (null === $timeout) {
            return INF;
        }
        if ($timeout <= 0.0 || !is_finite($timeout)) {
            throw new InvalidArgumentException('Timeout must be a positive finite float, or null to disable.');
        }

        return $timeout;
    }

    private static function validateWorkingDirectory(?string $cwd): ?string
    {
        if (null === $cwd) {
            return null;
        }
        if ('' === $cwd) {
            throw new InvalidArgumentException('Working directory must be a non-empty string or null.');
        }
        if (str_contains($cwd, "\0")) {
            throw new InvalidArgumentException('Working directory must not contain NUL bytes.');
        }
        if (!is_dir($cwd)) {
            throw new InvalidArgumentException(sprintf('Working directory does not exist: %s', $cwd));
        }
        if (!is_readable($cwd)) {
            throw new InvalidArgumentException(sprintf('Working directory is not readable: %s', $cwd));
        }
        // Resolve to the canonical path NOW and pass that to proc_open later.
        // This narrows the TOCTOU window between validation and actual spawn (CWE-367).
        $real = realpath($cwd);
        if (false === $real) {
            throw new InvalidArgumentException(sprintf('Working directory cannot be canonicalised: %s', $cwd));
        }

        return $real;
    }
}
