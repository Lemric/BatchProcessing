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

namespace Lemric\BatchProcessing\Bridge\Symfony\Profiler;

use DateTimeImmutable;
use Lemric\BatchProcessing\Domain\{JobExecution, StepExecution};
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/**
 * Symfony Web Profiler data collector for the Batch component.
 *
 * @phpstan-type CollectorData array{jobs: list<array<string, mixed>>, steps: list<array<string, mixed>>, errors: list<string>, http: array{method: string, path: string, status: int, exceptionClass: string|null}}
 */
final class BatchDataCollector extends DataCollector
{
    public function __construct(
        private readonly TraceableJobLauncher $traceableLauncher,
        private readonly TraceableJobRepository $traceableRepository,
    ) {
        $this->reset();
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $this->data = [
            'jobs' => array_map([$this, 'serializeJob'], $this->traceableLauncher->getCollectedExecutions()),
            'steps' => array_map([$this, 'serializeStep'], $this->traceableRepository->getCollectedSteps()),
            'errors' => $this->traceableLauncher->getCollectedErrors(),
            'http' => [
                'method' => $request->getMethod(),
                'path' => $request->getPathInfo(),
                'status' => $response->getStatusCode(),
                'exceptionClass' => null !== $exception ? $exception::class : null,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        /** @var list<string> $errors */
        $errors = $this->data['errors'] ?? [];

        return $errors;
    }

    public function getFailedJobCount(): int
    {
        return count(array_filter($this->getJobs(), static fn (array $j): bool => 'COMPLETED' !== ($j['status'] ?? '')));
    }

    public function getJobCount(): int
    {
        return count($this->getJobs());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getJobs(): array
    {
        /** @var list<array<string, mixed>> $jobs */
        $jobs = $this->data['jobs'] ?? [];

        return $jobs;
    }

    public function getName(): string
    {
        return 'lemric_batch';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSteps(): array
    {
        /** @var list<array<string, mixed>> $steps */
        $steps = $this->data['steps'] ?? [];

        return $steps;
    }

    public function reset(): void
    {
        $this->data = [
            'jobs' => [],
            'steps' => [],
            'errors' => [],
            'http' => ['method' => '', 'path' => '', 'status' => 0, 'exceptionClass' => null],
        ];
        $this->traceableLauncher->resetCollection();
        $this->traceableRepository->resetCollection();
    }

    private function durationMs(?DateTimeImmutable $start, ?DateTimeImmutable $end): ?float
    {
        if (null === $start || null === $end) {
            return null;
        }

        return ((float) $end->format('U.u') - (float) $start->format('U.u')) * 1000.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeJob(JobExecution $execution): array
    {
        return [
            'id' => $execution->getId(),
            'name' => $execution->getJobName(),
            'status' => $execution->getStatus()->value,
            'exitCode' => $execution->getExitStatus()->getExitCode(),
            'startTime' => $execution->getStartTime()?->format('Y-m-d H:i:s.u'),
            'endTime' => $execution->getEndTime()?->format('Y-m-d H:i:s.u'),
            'durationMs' => $this->durationMs($execution->getStartTime(), $execution->getEndTime()),
            'parameters' => $execution->getJobParameters()->toIdentifyingString(),
            'failures' => array_map(static fn (Throwable $e): string => get_class($e).': '.$e->getMessage(), $execution->getFailureExceptions()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStep(StepExecution $execution): array
    {
        return [
            'id' => $execution->getId(),
            'name' => $execution->getStepName(),
            'status' => $execution->getStatus()->value,
            'readCount' => $execution->getReadCount(),
            'writeCount' => $execution->getWriteCount(),
            'commitCount' => $execution->getCommitCount(),
            'rollbackCount' => $execution->getRollbackCount(),
            'skipCount' => $execution->getReadSkipCount() + $execution->getProcessSkipCount() + $execution->getWriteSkipCount(),
            'startTime' => $execution->getStartTime()?->format('Y-m-d H:i:s.u'),
            'endTime' => $execution->getEndTime()?->format('Y-m-d H:i:s.u'),
            'durationMs' => $this->durationMs($execution->getStartTime(), $execution->getEndTime()),
        ];
    }
}
