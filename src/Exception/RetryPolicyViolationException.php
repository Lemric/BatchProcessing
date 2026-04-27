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

use Lemric\BatchProcessing\Retry\RetryContext;
use Throwable;

class RetryPolicyViolationException extends RetryException
{
    public function __construct(
        string $message = '',
        private readonly ?RetryContext $retryContext = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryContext(): ?RetryContext
    {
        return $this->retryContext;
    }
}
