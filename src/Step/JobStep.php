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

use Lemric\BatchProcessing\Domain\{BatchStatus, StepExecution};
use Lemric\BatchProcessing\Job\{DefaultJobParametersExtractor, JobInterface, JobParametersExtractorInterface};
use Lemric\BatchProcessing\Launcher\JobLauncherInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;

/**
 * Step that runs a separate {@see JobInterface} as a child with optionally extracted parameters.
 */
final class JobStep extends AbstractStep
{
    private JobParametersExtractorInterface $parametersExtractor;

    public function __construct(
        string $name,
        JobRepositoryInterface $jobRepository,
        private readonly JobInterface $job,
        private readonly JobLauncherInterface $jobLauncher,
        ?JobParametersExtractorInterface $parametersExtractor = null,
    ) {
        parent::__construct($name, $jobRepository);
        $this->parametersExtractor = $parametersExtractor ?? new DefaultJobParametersExtractor();
    }

    public function setParametersExtractor(JobParametersExtractorInterface $extractor): void
    {
        $this->parametersExtractor = $extractor;
    }

    protected function doExecute(StepExecution $stepExecution): void
    {
        $params = $this->parametersExtractor->getJobParameters($this->job, $stepExecution);
        $childExecution = $this->jobLauncher->run($this->job, $params);

        if ($childExecution->getStatus()->isUnsuccessful()) {
            $stepExecution->setStatus(BatchStatus::FAILED);
            foreach ($childExecution->getFailureExceptions() as $e) {
                $stepExecution->addFailureException($e);
            }
        }
    }
}
