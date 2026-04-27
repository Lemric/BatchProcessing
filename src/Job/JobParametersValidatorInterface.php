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

use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Exception\JobParametersInvalidException;

/**
 * Strategy for validating {@see JobParameters} before a job is executed.
 */
interface JobParametersValidatorInterface
{
    /**
     * @throws JobParametersInvalidException when parameters are invalid
     */
    public function validate(JobParameters $parameters): void;
}
