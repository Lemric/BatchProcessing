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

namespace Lemric\BatchProcessing\Job;

use Lemric\BatchProcessing\Domain\{JobParameters, StepExecution};

/**
 * Default extractor: passes through the parent Job's parameters to the child.
 */
final class DefaultJobParametersExtractor implements JobParametersExtractorInterface
{
    /** @var list<string> */
    private array $keys = [];

    private bool $useAllParentParameters = true;

    /**
     * @param list<string> $keys limit to only these keys (empty = all)
     */
    public function __construct(array $keys = [], bool $useAllParentParameters = true)
    {
        $this->keys = $keys;
        $this->useAllParentParameters = $useAllParentParameters;
    }

    public function getJobParameters(JobInterface $job, StepExecution $stepExecution): JobParameters
    {
        $parentParams = $stepExecution->getJobExecution()->getJobParameters();

        if ($this->useAllParentParameters && [] === $this->keys) {
            return $parentParams;
        }

        $builder = new \Lemric\BatchProcessing\Domain\JobParametersBuilder();
        foreach ($this->keys as $key) {
            $param = $parentParams->get($key);
            if (null !== $param) {
                $builder->addParameter($param);
            }
        }

        if ($this->useAllParentParameters) {
            $builder->addJobParameters($parentParams);
        }

        return $builder->toJobParameters();
    }
}
