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

namespace Lemric\BatchProcessing\Domain;

/**
 * A logical instance of a Job, identified by the combination of {@code jobName} and the
 * identifying parameters supplied at launch time. Multiple {@see JobExecution}s may exist
 * for the same instance (one per actual run / restart attempt).
 */
final class JobInstance
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $jobName,
        private readonly string $jobKey,
        private int $version = 0,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobKey(): string
    {
        return $this->jobKey;
    }

    public function getJobName(): string
    {
        return $this->jobName;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function incrementVersion(): void
    {
        ++$this->version;
    }
}
