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

/**
 * Thrown when {@see \Lemric\BatchProcessing\Security\JobExecutionAccessCheckerInterface} denies access.
 */
final class JobExecutionAccessDeniedException extends JobExecutionException
{
    public function __construct()
    {
        parent::__construct('Job execution access denied.');
    }
}
