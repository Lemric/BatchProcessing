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

use Lemric\BatchProcessing\Job\{JobInterface, JobParametersExtractorInterface};
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\{JobStep, StepInterface};
use LogicException;

/**
 * {@code JobStepBuilder} parity. Wraps a child {@see JobInterface} as a step.
 *
 * @extends AbstractStepBuilder<JobStep>
 */
final class JobStepBuilder extends AbstractStepBuilder
{
    private ?JobInterface $childJob = null;

    private ?JobLauncherInterface $jobLauncher = null;

    private ?JobParametersExtractorInterface $parametersExtractor = null;

    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
    ) {
        parent::__construct($name, $jobRepository);
    }

    public function build(): StepInterface
    {
        if (null === $this->childJob) {
            throw new LogicException("JobStepBuilder for '{$this->name}' requires job().");
        }
        if (null === $this->jobLauncher) {
            throw new LogicException("JobStepBuilder for '{$this->name}' requires jobLauncher().");
        }

        $step = new JobStep(
            $this->name,
            $this->jobRepository,
            $this->childJob,
            $this->jobLauncher,
            $this->parametersExtractor,
        );

        $this->applyCommon($step);

        return $step;
    }

    public function job(JobInterface $job): self
    {
        $this->childJob = $job;

        return $this;
    }

    public function jobLauncher(JobLauncherInterface $launcher): self
    {
        $this->jobLauncher = $launcher;

        return $this;
    }

    public function parametersExtractor(JobParametersExtractorInterface $extractor): self
    {
        $this->parametersExtractor = $extractor;

        return $this;
    }
}
