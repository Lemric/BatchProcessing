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
 * Validates that all required keys are present and no unknown keys exist.
 */
final class DefaultJobParametersValidator implements JobParametersValidatorInterface
{
    /**
     * @param list<string> $requiredKeys
     * @param list<string> $optionalKeys
     */
    public function __construct(
        private readonly array $requiredKeys = [],
        private readonly array $optionalKeys = [],
    ) {
    }

    public function validate(JobParameters $parameters): void
    {
        $allParams = $parameters->getParameters();
        $errors = [];

        foreach ($this->requiredKeys as $key) {
            if (!isset($allParams[$key])) {
                $errors[] = sprintf('Required parameter "%s" is missing.', $key);
            }
        }

        if ([] !== $this->requiredKeys || [] !== $this->optionalKeys) {
            $allowedKeys = array_merge($this->requiredKeys, $this->optionalKeys);
            foreach (array_keys($allParams) as $key) {
                if (!in_array($key, $allowedKeys, true)) {
                    $errors[] = sprintf('Unknown parameter "%s".', $key);
                }
            }
        }

        if ([] !== $errors) {
            throw new JobParametersInvalidException(implode(' ', $errors));
        }
    }
}
