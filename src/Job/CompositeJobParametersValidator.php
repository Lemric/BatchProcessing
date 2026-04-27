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

/**
 * Validates {@see JobParameters} against a list of delegates.
 */
final class CompositeJobParametersValidator implements JobParametersValidatorInterface
{
    /**
     * @param list<JobParametersValidatorInterface> $validators
     */
    public function __construct(
        private readonly array $validators = [],
    ) {
    }

    public function validate(JobParameters $parameters): void
    {
        foreach ($this->validators as $validator) {
            $validator->validate($parameters);
        }
    }
}
