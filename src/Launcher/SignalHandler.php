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

namespace Lemric\BatchProcessing\Launcher;

use Lemric\BatchProcessing\Domain\JobExecution;
use Psr\Log\{LoggerInterface, NullLogger};

use const SIG_DFL;
use const SIGINT;
use const SIGTERM;

/**
 * Registers POSIX signal handlers (SIGTERM, SIGINT) that gracefully stop the running
 * JobExecution. CLI-only — no-ops on non-pcntl environments like PHP-FPM.
 *
 * Usage:
 *   $handler = new SignalHandler($jobExecution);
 *   $handler->register();
 *   // ... job runs ...
 *   $handler->unregister();
 */
final class SignalHandler
{
    private LoggerInterface $logger;

    /** @var list<int> */
    private array $signals = [];

    public function __construct(
        private readonly JobExecution $jobExecution,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function register(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        $this->signals = [SIGTERM, SIGINT];

        foreach ($this->signals as $signal) {
            pcntl_signal($signal, function (int $signo): void {
                $this->logger->info('Received signal '.$signo.', requesting stop.');
                $this->jobExecution->stop();
            });
        }

        pcntl_async_signals(true);
    }

    public function unregister(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        foreach ($this->signals as $signal) {
            pcntl_signal($signal, SIG_DFL);
        }
        $this->signals = [];
    }
}
