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

namespace Lemric\BatchProcessing\Step\Builder;

use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Retry\Backoff\{BackOffPolicyInterface, NoBackOffPolicy};
use Lemric\BatchProcessing\Retry\Policy\{NeverRetryPolicy, SimpleRetryPolicy};
use Lemric\BatchProcessing\Retry\{RetryOperations, RetryPolicyInterface, RetryTemplate};
use Lemric\BatchProcessing\Skip\{LimitCheckingItemSkipPolicy, NeverSkipItemSkipPolicy, SkipPolicyInterface};
use Lemric\BatchProcessing\Step\{ChunkOrientedStep, StepInterface};
use Lemric\BatchProcessing\Transaction\TransactionManagerInterface;
use LogicException;
use Throwable;

/**
 * {@code FaultTolerantStepBuilder} parity. Adds retry/skip configuration on top
 * of {@see SimpleStepBuilder} and produces a {@see ChunkOrientedStep} wired with
 * {@see RetryOperations} and a {@see SkipPolicyInterface}.
 *
 * @template TIn
 * @template TOut
 *
 * @extends SimpleStepBuilder<TIn, TOut>
 */
final class FaultTolerantStepBuilder extends SimpleStepBuilder
{
    private ?BackOffPolicyInterface $backOffPolicy = null;

    /** @var array<class-string<Throwable>, bool> */
    private array $noSkipExceptions = [];

    /** @var array<class-string<Throwable>, bool> */
    private array $retryableExceptions = [];

    private int $retryAttempts = 1;

    private ?RetryPolicyInterface $retryPolicy = null;

    private int $skipLimit = 0;

    /** @var array<class-string<Throwable>, bool> */
    private array $skippableExceptions = [];

    private ?SkipPolicyInterface $skipPolicy = null;

    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        ?TransactionManagerInterface $transactionManager = null,
    ) {
        parent::__construct($name, $jobRepository, $transactionManager);
    }

    public function backOff(BackOffPolicyInterface $policy): static
    {
        $this->backOffPolicy = $policy;

        return $this;
    }

    public function build(): StepInterface
    {
        if (null === $this->reader || null === $this->writer) {
            throw new LogicException("FaultTolerantStepBuilder for '{$this->name}' requires reader() and writer().");
        }

        $step = new ChunkOrientedStep(
            name: $this->name,
            jobRepository: $this->jobRepository,
            reader: $this->reader,
            processor: $this->processor,
            writer: $this->writer,
            chunkSize: $this->chunkSize,
            transactionManager: $this->transactionManager,
            retryOperations: $this->buildRetryOperations(),
            skipPolicy: $this->buildSkipPolicy(),
            completionPolicy: $this->completionPolicy,
        );

        foreach ($this->streams as $stream) {
            $step->registerStream($stream);
        }

        $this->applyCommon($step);

        return $step;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function noRetry(string $exceptionClass): static
    {
        $this->retryableExceptions[$exceptionClass] = false;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function noSkip(string $exceptionClass): static
    {
        $this->noSkipExceptions[$exceptionClass] = true;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function retry(string $exceptionClass, int $maxAttempts = 3): static
    {
        $this->retryableExceptions[$exceptionClass] = true;
        $this->retryAttempts = max($this->retryAttempts, $maxAttempts);

        return $this;
    }

    public function retryPolicy(RetryPolicyInterface $policy): static
    {
        $this->retryPolicy = $policy;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function skip(string $exceptionClass): static
    {
        $this->skippableExceptions[$exceptionClass] = true;

        return $this;
    }

    public function skipLimit(int $limit): static
    {
        $this->skipLimit = $limit;

        return $this;
    }

    public function skipPolicy(SkipPolicyInterface $policy): static
    {
        $this->skipPolicy = $policy;

        return $this;
    }

    private function buildRetryOperations(): RetryOperations
    {
        if (null !== $this->retryPolicy) {
            return new RetryTemplate($this->retryPolicy, $this->backOffPolicy ?? new NoBackOffPolicy());
        }

        if ([] === $this->retryableExceptions) {
            return new RetryTemplate(new NeverRetryPolicy(), new NoBackOffPolicy());
        }

        return new RetryTemplate(
            new SimpleRetryPolicy($this->retryAttempts, $this->retryableExceptions),
            $this->backOffPolicy ?? new NoBackOffPolicy(),
        );
    }

    private function buildSkipPolicy(): SkipPolicyInterface
    {
        if (null !== $this->skipPolicy) {
            return $this->skipPolicy;
        }

        if ([] === $this->skippableExceptions) {
            return new NeverSkipItemSkipPolicy();
        }

        if ($this->skipLimit <= 0) {
            throw new LogicException('skipLimit must be > 0 when skippable exceptions are configured (call skipLimit()).');
        }

        $merged = $this->skippableExceptions;
        foreach (array_keys($this->noSkipExceptions) as $class) {
            $merged[$class] = false;
        }

        return new LimitCheckingItemSkipPolicy(
            $this->skipLimit,
            $merged,
        );
    }
}
