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

use DateTimeImmutable;
use Lemric\BatchProcessing\Domain\{JobParameters, JobParametersBuilder};

/**
 * Incrementer that sets a "run.date" parameter to the current timestamp.
 */
final class DateIncrementer implements JobParametersIncrementerInterface
{
    public function __construct(
        private readonly string $key = 'run.date',
    ) {
    }

    public function getNext(?JobParameters $previous): JobParameters
    {
        $builder = new JobParametersBuilder($previous);
        $builder->addDate($this->key, new DateTimeImmutable());

        return $builder->toJobParameters();
    }
}
