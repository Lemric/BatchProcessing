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

namespace Lemric\BatchProcessing\Security;

/**
 * Optional authorization hook for CLI and other operators that act on a {@code JobExecution} id.
 *
 * Applications register a real implementation (e.g. tenant / owner checks). The default
 * {@see NoOpJobExecutionAccessChecker} permits all access.
 */
interface JobExecutionAccessCheckerInterface
{
    /**
     * @throws \Lemric\BatchProcessing\Exception\JobExecutionAccessDeniedException when the current security principal may not access this execution
     */
    public function assertMayAccessJobExecution(int $jobExecutionId): void;
}
