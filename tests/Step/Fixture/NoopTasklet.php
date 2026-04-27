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

namespace Lemric\BatchProcessing\Tests\Step\Fixture;

use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\StepContribution;
use Lemric\BatchProcessing\Step\{RepeatStatus, TaskletInterface};

final class NoopTasklet implements TaskletInterface
{
    public function execute(StepContribution $contribution, ChunkContext $context): RepeatStatus
    {
        return RepeatStatus::FINISHED;
    }
}
