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

namespace Lemric\BatchProcessing\Exception;

use Lemric\BatchProcessing\Domain\BatchStatus;
use Throwable;

class JobInterruptedException extends StepExecutionException
{
    public function __construct(
        string $message = '',
        private readonly BatchStatus $status = BatchStatus::STOPPED,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatus(): BatchStatus
    {
        return $this->status;
    }
}
