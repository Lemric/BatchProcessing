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
 * Applies multiple incrementers in sequence.
 */
final class CompositeIncrementer implements JobParametersIncrementerInterface
{
    /**
     * @param list<JobParametersIncrementerInterface> $incrementers
     */
    public function __construct(
        private readonly array $incrementers = [],
    ) {
    }

    public function getNext(?JobParameters $previous): JobParameters
    {
        $params = $previous;
        foreach ($this->incrementers as $incrementer) {
            $params = $incrementer->getNext($params);
        }

        return $params ?? JobParameters::empty();
    }
}
