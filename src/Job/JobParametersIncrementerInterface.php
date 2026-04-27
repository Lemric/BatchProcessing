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
 * Strategy used by the {@see SimpleJobLauncher} to derive the next set of parameters to use
 * when no explicit identifying parameters are supplied (e.g. for periodic jobs).
 */
interface JobParametersIncrementerInterface
{
    public function getNext(?JobParameters $previous): JobParameters;
}
