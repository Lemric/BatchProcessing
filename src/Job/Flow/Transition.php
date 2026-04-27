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

namespace Lemric\BatchProcessing\Job\Flow;

/**
 * Represents a single transition in a flow: from step exit code to next action.
 */
final readonly class Transition
{
    private function __construct(
        private ?string $to,
        private string $status,
        private bool $stop,
        private bool $fail,
        private bool $end,
    ) {
    }

    /**
     * End the flow with the given status.
     */
    public static function end(string $exitStatus = 'COMPLETED'): self
    {
        return new self(null, $exitStatus, false, false, true);
    }

    /**
     * Fail the flow.
     */
    public static function fail(): self
    {
        return new self(null, 'FAILED', false, true, false);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    public function isEnd(): bool
    {
        return $this->end;
    }

    public function isFail(): bool
    {
        return $this->fail;
    }

    public function isStop(): bool
    {
        return $this->stop;
    }

    /**
     * Stop the flow (can be restarted).
     */
    public static function stop(): self
    {
        return new self(null, 'STOPPED', true, false, false);
    }

    /**
     * Stop and allow restart from a specific step.
     */
    public static function stopAndRestart(string $restartStepName): self
    {
        return new self($restartStepName, 'STOPPED', true, false, false);
    }

    /**
     * Transition to another step.
     */
    public static function to(string $nextStepName): self
    {
        return new self($nextStepName, 'COMPLETED', false, false, false);
    }
}
